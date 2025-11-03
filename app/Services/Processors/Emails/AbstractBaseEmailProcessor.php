<?php

namespace App\Services\Processors\Emails;

use Exception;
use App\Services\Email\Dto\EmailMessageDTO;
use App\Services\Email\Contracts\EmailClient;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\Processors\Emails\Support\PreflightResult;
use App\Enums\EmailProcessing\{ProcessorStatus, EmailStatus};

/**
 * Classe de base abstraite pour tous les processeurs d'email
 * Contient la logique commune entre Draft et EmailIn
 */
abstract class AbstractBaseEmailProcessor implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected EmailClient $emailClient;
    protected EmailMessageDTO $emailData;

    public int $tries = 2;
    public int $backoff = 5;

    // Champs système réservés - interdits aux processeurs
    protected const RESERVED_FIELDS = [
        'status',
        'message',
        'started_at',
        'ended_at',
        'finished_at'
    ];

    // --- Métadonnées service (à implémenter par les sous-classes) ---
    abstract public static function getKey(): string;
    abstract public static function getIcon(): string;
    abstract public static function getLabel(): string;
    abstract public static function getDescription(): string;
    abstract public static function getDefaultTriggerCode(): string;

    // --- Phase 1 (synchrone) ---
    abstract public function preflight(): PreflightResult;

    // --- Méthodes d'accès aux données (à implémenter par Draft/EmailIn) ---
    abstract protected function getServiceOption(string $key, mixed $default = null): mixed;
    abstract protected function getResult(string $key, mixed $default = null): mixed;
    abstract protected function setResult(string $key, mixed $value): void;
    abstract protected function getUser();
    abstract protected function getEmail();

    // --- Configuration du processeur ---

    /**
     * Indique si ce processeur supporte l'exécution en queue
     * Par défaut true, les processeurs preflight-only retournent false
     */
    public static function supportsQueue(): bool
    {
        return true;
    }

    /**
     * Indique si le regex est requis pour ce processeur
     * Par défaut true, peut être surchargé pour rendre le regex optionnel
     */
    public static function requiresRegex(): bool
    {
        return true;
    }

    // --- Bloc service (statut + facts) ---
    protected function getServiceKey(): string
    {
        return static::getKey();
    }

    protected function updateProcessorStatus(ProcessorStatus $status, ?string $message = null): void
    {
        $email = $this->getEmail();
        $key = $this->getServiceKey();
        $entry = $email->getServiceResult($key, null, []) ?? [];

        // Champs système seulement
        $entry['status'] = $status->value;
        if ($message !== null) {
            $entry['message'] = $message;
        }
        $entry['started_at'] = $entry['started_at'] ?? now()->toISOString();

        if (in_array($status, [ProcessorStatus::Success, ProcessorStatus::Blocked, ProcessorStatus::Error], true)) {
            $entry['ended_at'] = now()->toISOString();
        }

        $email->setServiceResult($key, null, $entry);
    }

    protected function finishProcessor(ProcessorStatus $final, array $data = [], ?string $message = null): void
    {
        $email = $this->getEmail();

        // Message automatique selon le statut si pas fourni
        $finalMessage = $message ?? match ($final) {
            ProcessorStatus::Success => 'Traitement terminé avec succès',
            ProcessorStatus::Blocked => 'Traitement bloqué',
            ProcessorStatus::Error => 'Erreur lors du traitement',
            default => null,
        };

        // Enregistrer les données métier directement
        foreach ($data as $key => $value) {
            $this->setResult($key, $value);
        }

        // Mettre à jour le statut système
        $this->updateProcessorStatus($final, $finalMessage);

        if ($email->active_jobs === null) $email->active_jobs = 0;
        $email->active_jobs = max(0, $email->active_jobs - 1);

        if ($final === ProcessorStatus::Error) {
            $email->has_error = true;
        }

        $this->recomputeEmailStatus();
        $email->save();
    }

    protected function recomputeEmailStatus(): void
    {
        $email = $this->getEmail();

        if (($email->active_jobs ?? 0) > 0) {
            $email->status = EmailStatus::Processing->value;
            return;
        }

        $results  = $email->services_results ?? [];
        $statuses = array_map(fn($e) => $e['status'] ?? ProcessorStatus::Idle->value, $results);

        if ($email->has_error) {
            $email->status = EmailStatus::Error->value;
            return;
        }

        // Toujours End si pas d'erreur globale et plus de jobs actifs
        $email->status = EmailStatus::End->value;

        if ($email->status === EmailStatus::End->value) {
            $email->finished_at = now();
        }
    }

    // --- Guards + regex (avec support optionnel) ---
    protected function guardAndCaptureCode(): ?PreflightResult
    {
        $mode = $this->getServiceOption('mode', 'inactif');
        if ($mode === 'inactif') {
            return PreflightResult::blocked('Service désactivé');
        }

        // Si le regex n'est pas requis, on passe directement
        if (!static::requiresRegex()) {
            $this->setResult('mode', $mode);
            $this->setResult('code', null);
            $this->setResult('code_options', []);
            return null;
        }

        // Vérification du regex si requis
        $expected = $this->getServiceOption('regex_code', static::getDefaultTriggerCode());
        $actualCode = $this->emailData->regexCode ?? null;

        if ($actualCode !== $expected) {
            return PreflightResult::blocked("Code incorrect : '{$actualCode}' (attendu: '{$expected}')");
        }

        // Stocké pour la queue et pour les InfoLists résultats
        $this->setResult('mode', $mode);
        $this->setResult('code', $actualCode);
        $this->setResult('code_options', $this->emailData->regexCodeOption ?? []);

        return null;
    }

    protected function beginProcessor(): void
    {
        $email = $this->getEmail();
        $key = $this->getServiceKey();
        $cur = $email->getServiceResult($key, null, []);

        if ($cur && in_array(($cur['status'] ?? ''), [
            ProcessorStatus::Success->value,
            ProcessorStatus::Blocked->value,
            ProcessorStatus::Error->value
        ], true)) {
            return; // déjà terminé
        }

        $this->updateProcessorStatus(ProcessorStatus::Processing, 'Traitement en cours...');

        if ($email->active_jobs === null) $email->active_jobs = 0;
        $email->active_jobs++;
        $email->status = EmailStatus::Processing->value;
        $email->save();
    }

    // --- Helpers HTML/body (spécifiques aux Draft) ---
    protected function insertInRegexKey(string $body, string $replacement): string
    {
        return preg_replace('/##\s*(.+?)\s*##/', "## {$replacement} ##", $body, 1);
    }

    protected function replaceRegexKey(string $body, string $replacement): string
    {
        return preg_replace('/##\s*(.+?)\s*##/', $replacement, $body, 1);
    }

    protected function removeRegexKeyAndLineIfEmptyHTML(string $htmlText): string
    {
        $pattern = '/##\s*(.+?)\s*##/';
        $lines = preg_split('/\r\n|\r|\n/', $htmlText);
        foreach ($lines as $i => $line) {
            $lineWithout = preg_replace($pattern, '', $line);
            $lines[$i] = trim($lineWithout) === '' ? null : $lineWithout;
        }
        return implode("\n", array_filter($lines, fn($l) => $l !== null));
    }
}
