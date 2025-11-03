<?php

namespace App\Services\Processors\Emails;

use Exception;
use App\Models\MsgUserIn;
use App\Models\MsgEmailIn;
use App\Services\Email\Dto\EmailMessageDTO;
use App\Services\Email\Contracts\EmailClient;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\Processors\Emails\Support\PreflightResult;
use App\Enums\EmailProcessing\{ProcessorStatus, EmailStatus};

abstract class BaseEmailInProcessor implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected EmailClient $emailClient;
    protected MsgUserIn $user;
    protected EmailMessageDTO $emailData;
    protected MsgEmailIn $email;

    public int $tries = 2;
    public int $backoff = 5;

    // Champs système réservés - interdits aux processeurs
    protected const RESERVED_FIELDS = [
        'mode',
        'status',
        'message',
        'started_at',
        'finished_at'
    ];

    // --- Constructor ---
    public function __construct(MsgUserIn $user, EmailMessageDTO $emailData, MsgEmailIn $email, EmailClient $emailClient)
    {
        $this->user = $user;
        $this->emailData = $emailData;
        $this->email = $email;
        $this->emailClient = $emailClient;
    }

    // --- Méthodes abstraites à implémenter ---
    abstract public static function getKey(): string;
    abstract public static function getIcon(): string;
    abstract public static function getLabel(): string;
    abstract public static function getDescription(): string;
    abstract public static function getDefaultTriggerCode(): string;
    abstract public static function getDefaults(): array;
    abstract public static function getForm(): array;
    abstract public static function getInfoList(): array;
    abstract public static function getResultsInfoList(): array;
    abstract public function preflight(): PreflightResult;
    abstract protected function perform(): MsgEmailIn;

    // --- Méthodes publiques ---
    public function handle(): void
    {
        $this->perform();
    }

    // --- Helpers pour la gestion des résultats ---
    public function getResult(string $key, $default = null)
    {
        return $this->email->getServiceResult(static::getKey(), $key, $default);
    }

    public function setResult(string $key, $value): void
    {
        if (in_array($key, self::RESERVED_FIELDS)) {
            throw new Exception("Champ réservé : {$key}");
        }
        $this->email->setServiceResult(static::getKey(), $key, $value);
    }

    public function getServiceOption(string $key, $default = null)
    {
        return $this->user->getServiceOption(static::getKey(), $key, $default);
    }

    public function setServiceOption(string $key, $value): void
    {
        $this->user->setServiceOption(static::getKey(), $key, $value);
    }

    // --- Gestion des statuts ---
    protected function updateProcessorStatus(ProcessorStatus $status, string $message = null): void
    {
        $serviceKey = static::getKey();
        $results = $this->email->services_results ?? [];

        $results[$serviceKey]['status'] = $status->value;
        $results[$serviceKey]['message'] = $message;
        $results[$serviceKey]['started_at'] = $results[$serviceKey]['started_at'] ?? now()->toISOString();

        if ($status !== ProcessorStatus::Processing) {
            $results[$serviceKey]['finished_at'] = now()->toISOString();
        }

        $this->email->services_results = $results;
    }

    protected function finishProcessor(ProcessorStatus $status, array $data = [], ?string $message = null): void
    {
        // Message automatique selon le statut si pas fourni
        $finalMessage = $message ?? match ($status) {
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
        $this->updateProcessorStatus($status, $finalMessage);

        if ($this->email->active_jobs === null) $this->email->active_jobs = 0;
        $this->email->active_jobs = max(0, $this->email->active_jobs - 1);

        if ($status === ProcessorStatus::Error) {
            $this->email->has_error = true;
        }

        $this->recomputeEmailStatus();
        $this->email->save();
    }

    protected function recomputeEmailStatus(): void
    {
        if (($this->email->active_jobs ?? 0) > 0) {
            $this->email->status = EmailStatus::Processing->value;
            return;
        }

        $results  = $this->email->services_results ?? [];
        $statuses = array_map(fn($e) => $e['status'] ?? ProcessorStatus::Idle->value, $results);

        if ($this->email->has_error) {
            $this->email->status = EmailStatus::Error->value;
            return;
        }

        // Toujours End si pas d'erreur globale et plus de jobs actifs
        $this->email->status = EmailStatus::End->value;

        if ($this->email->status === EmailStatus::End->value) {
            $this->email->finished_at = now();
        }
    }

    // Guards + placeholder
    protected function guardAndCaptureCode(): ?PreflightResult
    {
        $mode = $this->getServiceOption('mode', 'inactif');
        if ($mode === 'inactif') {
            return PreflightResult::blocked('Service inactif');
        }

        // Guard regex code logic (similar to BaseEmailDraftProcessor)
        $regexCode = $this->getServiceOption('regex_code', static::getDefaultTriggerCode());
        if (empty($regexCode)) {
            return PreflightResult::blocked('Code de déclenchement manquant');
        }

        // TODO: Implement regex capture logic for EmailIn if needed
        $this->setResult('mode', $mode);
        $this->setResult('code_options', []);

        return null; // OK
    }

    protected function beginProcessor(): void
    {
        $this->updateProcessorStatus(ProcessorStatus::Processing, 'Traitement en cours...');

        if ($this->email->active_jobs === null) $this->email->active_jobs = 0;
        $this->email->active_jobs++;
        $this->email->save();
    }

    protected function startProcessingWithPlaceholder(): void
    {
        $this->setResult('started_at', now()->toISOString());
    }

    // --- Helpers HTML et regex (adaptés de BaseEmailDraftProcessor) ---
    protected function removeRegexKeyAndLineIfEmptyHTML(string $html): string
    {
        $regexCode = $this->getServiceOption('regex_code', static::getDefaultTriggerCode());

        // Remove the trigger code
        $cleanedHtml = str_replace("##$regexCode##", '', $html);

        // Remove empty lines
        return preg_replace('/^\s*$/m', '', $cleanedHtml);
    }
}
