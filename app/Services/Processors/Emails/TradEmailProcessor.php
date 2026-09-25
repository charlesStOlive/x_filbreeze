<?php

namespace App\Services\Processors\Emails;

use App\Ai\EmailTranslation;
use CharlesStOlive\FilamentPrism\Models\AiInteraction;
use CharlesStOlive\FilamentPrism\Services\AiRunner;
use CharlesStOlive\MsGraphFilament\Enums\ProcessorStatus;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;

/**
 * Traduit un brouillon Outlook qui commence par `## traduit -en ##` : la langue cible est la
 * première option (voir `AiDraftProcessor` pour -n, le mode test et l'historique conservé).
 *
 * L'appel à l'IA est décrit par la ressource `App\Ai\EmailTranslation` (prompt, modèle via
 * `AI_TRANSLATION_MODEL`).
 */
class TradEmailProcessor extends AiDraftProcessor
{
    public static function getKey(): string
    {
        return 'd-trad';
    }
    public static function getIcon(): string
    {
        return 'heroicon-o-language';
    }
    public static function getLabel(): string
    {
        return 'Traduire le mail';
    }
    public static function getDescription(): string
    {
        return 'Traduit le mail vers la langue indiquée dans le code (ex. ## traduit -en ##)';
    }
    public static function getDefaultTriggerCode(): string
    {
        return 'traduit';
    }

    public static function getDefaults(): array
    {
        return [
            'mode' => 'inactif',
            'regex_code' => 'traduit',
        ];
    }

    // --- UI pour ton builder ---
    public static function getForm(): array
    {
        return [
            TextInput::make('regex_code')
                ->label('Code de déclenchement')
                ->helperText('Code qui doit être présent dans l\'email pour déclencher la traduction, suivi de la langue cible (ex. traduit -en)')
                ->visible(fn($get) => in_array($get('mode'), ['actif', 'test'])),
        ];
    }

    public static function getInfoList(): array
    {
        return [
            TextEntry::make('regex_code')
                ->label('Code de déclenchement')
                ->formatStateUsing(fn($state) => "## {$state} ##")
                ->badge()
                ->color('gray')
                ->copyable()
                ->copyMessage('Code de déclenchement copié!')
                ->icon('heroicon-o-hashtag'),
        ];
    }

    public static function getResultsInfoList(): array
    {
        return [
            TextEntry::make('target_language')
                ->label('Langue cible')
                ->badge()
                ->color('info')
                ->icon('heroicon-o-language')
                ->visible(fn($state) => !empty($state)),

            ...static::commonResultsInfoList('Contenu traduit'),
        ];
    }

    /**
     * Phase 1: Action spécifique avant mise en queue
     * Bloque tout de suite si le code n'indique pas de langue, sinon marque le draft comme en
     * cours de traitement
     */
    protected function actionDraftBeforeQueue(): ProcessorStatus
    {
        if ($this->targetLanguage() === null) {
            return $this->updateProcessorStatus(
                ProcessorStatus::Blocked,
                'Langue cible absente : ajouter une option au code, ex. ## traduit -en ##'
            );
        }

        $this->markDraftAsProcessing();
        return ProcessorStatus::Success;
    }

    protected function runAi(string $message): AiInteraction
    {
        $this->setResult('target_language', $this->targetLanguage());

        return app(AiRunner::class)->run(
            app(EmailTranslation::class),
            input: ['html' => $message, 'language' => $this->targetLanguage()],
            attachTo: $this->email,
        );
    }

    protected function outputHtml(AiInteraction $interaction): string
    {
        return $interaction->output['text'] ?? '';
    }

    protected function successMessage(string $processingMode): string
    {
        return match ($processingMode) {
            'test'      => "Traduction testée vers {$this->targetLanguage()}",
            'new_draft' => "Nouveau brouillon traduit vers {$this->targetLanguage()} créé",
            default     => "Email traduit vers {$this->targetLanguage()} et mis à jour",
        };
    }

    /** La langue cible : la première option du code qui n'est pas -n (## traduit -n -en ## → en) */
    private function targetLanguage(): ?string
    {
        return $this->codeOptions()[0] ?? null;
    }
}
