<?php

namespace App\Services\Processors\Emails;

use Exception;
use RuntimeException;
use App\Ai\EmailTranslation;
use CharlesStOlive\FilamentPrism\Services\AiRunner;
use CharlesStOlive\MsGraphFilament\Models\MsgEmailDraft;
use CharlesStOlive\MsGraphFilament\Enums\ProcessorStatus;
use CharlesStOlive\MsGraphFilament\Processors\BaseEmailDraftProcessor;
use CharlesStOlive\MsGraphFilament\Support\DraftBody;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;

/**
 * Traduit un brouillon Outlook qui commence par `## traduit -en ##` (la langue cible est la
 * première option ; `-n` crée un nouveau brouillon au lieu de modifier celui-ci). Seul le
 * message rédigé est traduit, l'historique de la conversation reste intact (`DraftBody`).
 *
 * L'appel à l'IA est décrit par la ressource `App\Ai\EmailTranslation` (prompt, modèle via
 * `AI_TRANSLATION_MODEL`) ; ce processeur ne fait que l'appeler et appliquer le résultat.
 */
class TradEmailProcessor extends BaseEmailDraftProcessor
{
    /** L'option qui demande un nouveau brouillon : ce n'est pas une langue. */
    private const NEW_DRAFT_OPTION = 'n';

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

            TextEntry::make('translated_content')
                ->label('Contenu traduit')
                ->limit(1000)
                ->tooltip(fn($state) => $state)
                ->visible(fn($state) => !empty($state)),

            TextEntry::make('processing_mode')
                ->label('Mode de traitement')
                ->badge()
                ->color(fn($state) => match ($state) {
                    'new_draft' => 'success',
                    'update'    => 'warning',
                    'test'      => 'info',
                    default     => 'gray'
                })
                ->visible(fn($state) => !empty($state)),

            TextEntry::make('new_draft_id')
                ->label('ID nouveau brouillon')
                ->copyable()
                ->copyMessage('ID copié!')
                ->icon('heroicon-o-document-duplicate')
                ->visible(fn($state) => !empty($state)),

            TextEntry::make('tokens')
                ->label('Tokens consommés')
                ->icon('heroicon-o-cpu-chip')
                ->visible(fn($state) => !empty($state)),
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

    /**
     * Phase 2: Action durant la queue
     * Traduction du brouillon avec l'IA
     */
    protected function perform(): MsgEmailDraft
    {
        try {
            $language  = $this->targetLanguage();
            $createNew = (bool) ($this->getResult('code_options', [])[self::NEW_DRAFT_OPTION] ?? false);

            // Seul le message rédigé est traduit : l'historique de la conversation cité en
            // dessous (et l'enveloppe HTML du document) est remis tel quel autour de la traduction
            $body = DraftBody::fromHtml($this->removeRegexKeyAndLineIfEmptyHTML($this->emailData->bodyHtml));

            if ($body->messageIsEmpty()) {
                throw new RuntimeException('Rien à traduire : le brouillon ne contient pas de message au-dessus de l\'historique');
            }

            // Rattachée au brouillon : si le job est relancé avec le même contenu, la réponse
            // déjà payée est réutilisée
            $interaction = app(AiRunner::class)->run(
                app(EmailTranslation::class),
                input: [
                    'html'     => $body->message,
                    'language' => $language,
                ],
                attachTo: $this->email,
            );

            $translated = $interaction->output['text'] ?? '';

            if ($translated === '') {
                throw new RuntimeException('L\'IA a renvoyé une traduction vide');
            }

            // ✅ Stocker les résultats AVANT finishProcessor
            $this->setResult('target_language', $language);
            $this->setResult('translated_content', $translated);
            $this->setResult('tokens', $interaction->total_tokens);

            $translatedBody = $body->withMessage($translated);

            if ($this->isTestMode()) {
                // Rien n'est écrit dans Outlook : la traduction n'est pas appliquée
                $interaction->markDiscarded();
                $this->setResult('processing_mode', 'test');
                $this->finishProcessor(ProcessorStatus::Success, "Traduction testée vers {$language}");
                return $this->email;
            }

            if ($createNew) {
                // Option -n : Créer un nouveau brouillon
                $resp = $this->emailClient->createDraft($this->user, $this->emailData->with(['bodyHtml' => $translatedBody]));
                $this->setResult('new_draft_id', $resp['id'] ?? null);
                $this->setResult('processing_mode', 'new_draft');
                $message = "Nouveau brouillon traduit vers {$language} créé";
            } else {
                // Par défaut : Update du brouillon existant
                $this->updateBody($translatedBody);
                $this->setResult('processing_mode', 'update');
                $message = "Email traduit vers {$language} et mis à jour";
            }

            $this->markDraftAsCompleted();
            $interaction->markApplied();

            $this->finishProcessor(ProcessorStatus::Success, $message);
        } catch (Exception $e) {
            $this->finishProcessor(ProcessorStatus::Error, $e->getMessage());
        }

        return $this->email;
    }

    /**
     * La langue cible : la première option du code qui n'est pas -n (## traduit -en -n ## → en)
     */
    private function targetLanguage(): ?string
    {
        $options = array_keys($this->getResult('code_options', []) ?? []);
        $language = collect($options)->first(fn($option) => $option !== self::NEW_DRAFT_OPTION);

        return $language === null ? null : (string) $language;
    }
}
