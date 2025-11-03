<?php

namespace App\Services\Processors\Emails;

use Exception;
use App\Models\MsgUserDraft;
use App\Models\MsgEmailDraft;
use App\Services\Email\Dto\EmailMessageDTO;
use App\Services\Email\Contracts\EmailClient;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\Processors\Emails\Support\PreflightResult;
use App\Enums\EmailProcessing\{ProcessorStatus, EmailStatus};

abstract class BaseEmailDraftProcessor implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected EmailClient $emailClient;
    protected MsgUserDraft $user;
    protected EmailMessageDTO $emailData;
    protected MsgEmailDraft $email;

    public int $tries = 2;
    public int $backoff = 5;

    // Champs système réservés - interdits aux processeurs
    protected const RESERVED_FIELDS = [
        'status',
        'message',
        'started_at',
        'ended_at',
    ];

    public function __construct(
        MsgUserDraft $user,
        EmailMessageDTO $emailData,
        MsgEmailDraft $email,
        ?EmailClient $emailClient = null
    ) {
        $this->emailClient = $emailClient ?: app(EmailClient::class);
        $this->user = $user;
        $this->emailData = $emailData;
        $this->email = $email;
    }

    // Métadonnées service
    abstract public static function getKey(): string;
    abstract public static function getIcon(): string;
    abstract public static function getLabel(): string;
    abstract public static function getDescription(): string;
    abstract public static function getDefaultTriggerCode(): string;

    // Phase 1 (synchrone)
    abstract public function preflight(): PreflightResult;

    // Phase 2 (queue)
    abstract protected function perform(): MsgEmailDraft;

    // Orchestration queue
    public function handle(): void
    {
        try {
            if (in_array($this->email->status, [EmailStatus::End->value, EmailStatus::Error->value], true)) {
                return;
            }
            $this->perform();
        } catch (Exception $e) {
            // marque l'erreur + agrège global
            $this->finishProcessor(ProcessorStatus::Error, [], $e->getMessage());
        }
    }

    public static function onQueue(MsgUserDraft $user, EmailMessageDTO $emailData, MsgEmailDraft $email): void
    {
        dispatch(new static($user, $emailData, $email));
    }

    // Options & Results par clé
    protected function getServiceOption(string $key, mixed $default = null): mixed
    {
        return $this->user->getServiceOption(static::getKey(), $key, $default);
    }
    protected function getResult(string $key, mixed $default = null): mixed
    {
        return $this->email->getServiceResult(static::getKey(), $key, $default);
    }
    protected function setResult(string $key, mixed $value): void
    {
        $this->email->setServiceResult(static::getKey(), $key, $value);
    }

    // Bloc service (statut + facts)
    protected function getServiceKey(): string
    {
        return static::getKey();
    }

    protected function updateProcessorStatus(ProcessorStatus $status, ?string $message = null): void
    {
        $key = $this->getServiceKey();
        $entry = $this->email->getServiceResult($key, null, []) ?? [];

        // Champs système seulement
        $entry['status'] = $status->value;
        if ($message !== null) {
            $entry['message'] = $message;
        }
        $entry['started_at'] = $entry['started_at'] ?? now()->toISOString();

        if (in_array($status, [ProcessorStatus::Success, ProcessorStatus::Blocked, ProcessorStatus::Error], true)) {
            $entry['ended_at'] = now()->toISOString();
        }

        $this->email->setServiceResult($key, null, $entry);
    }

    protected function beginProcessor(): void
    {
        $key = $this->getServiceKey();
        $cur = $this->email->getServiceResult($key, null, []);
        if ($cur && in_array(($cur['status'] ?? ''), [
            ProcessorStatus::Success->value,
            ProcessorStatus::Blocked->value,
            ProcessorStatus::Error->value
        ], true)) {
            return; // déjà terminé
        }

        $this->updateProcessorStatus(ProcessorStatus::Processing, 'Traitement en cours...');

        if ($this->email->active_jobs === null) $this->email->active_jobs = 0;
        $this->email->active_jobs++;
        $this->email->status = EmailStatus::Processing->value;
        $this->email->save();
    }

    protected function finishProcessor(ProcessorStatus $final, array $data = [], ?string $message = null): void
    {
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

        if ($this->email->active_jobs === null) $this->email->active_jobs = 0;
        $this->email->active_jobs = max(0, $this->email->active_jobs - 1);

        if ($final === ProcessorStatus::Error) {
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
            return PreflightResult::blocked('Service désactivé');
        }

        $expected = $this->getServiceOption('regex_code', static::getDefaultTriggerCode());
        if (($this->emailData->regexCode ?? null) !== $expected) {
            return PreflightResult::blocked("Code incorrect : '{$this->emailData->regexCode}' (attendu: '{$expected}')");
        }

        // stocké pour la queue et pour tes InfoLists résultats
        $this->setResult('mode', $mode);
        $this->setResult('code', $this->emailData->regexCode);
        $this->setResult('code_options', $this->emailData->regexCodeOption ?? []);
        return null;
    }

    protected function startProcessingWithPlaceholder(): void
    {
        $mode = $this->getResult('mode', 'inactif');
        if ($mode !== 'test') {
            $newBody = $this->insertInRegexKey($this->emailData->bodyHtml, 'Je travaille');
            $this->emailClient->updateEmail($this->user, $this->email, [
                'body' => ['contentType' => $this->emailData->contentType, 'content' => $newBody],
            ]);
        }
        $this->email->save();
    }

    // helpers HTML/body
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

    protected function updateBody(string $content): void
    {
        $this->emailClient->updateEmail($this->user, $this->email, [
            'body' => ['contentType' => $this->emailData->contentType, 'content' => $content],
        ]);
    }
    protected function markOriginalProcessed(string $message = 'Terminé'): void
    {
        $updated = $this->insertInRegexKey($this->emailData->bodyHtml, $message);
        $this->updateBody($updated);
    }
}
