<?php

namespace App\Services\Processors\Emails;

use Exception;
use App\Services\Prism\PrismTextService;
use CharlesStOlive\MsGraphFilament\Models\MsgEmailDraft;
use CharlesStOlive\MsGraphFilament\Enums\ProcessorStatus;
use CharlesStOlive\MsGraphFilament\Processors\BaseEmailDraftProcessor;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;

class TradEmailProcessor extends BaseEmailDraftProcessor
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
        return 'Traduit le mail de la langue source vers la langue cible';
    }
    public static function getDefaultTriggerCode(): string
    {
        return 'traduit';
    }

    public static function getDefaults(): array
    {
        return [
            'mode' => 'inactif',
            'model' => config('ai.translation.model', 'gpt-5.4-nano'),
            'regex_code' => 'traduit',
        ];
    }

    // --- UI pour ton builder ---
    public static function getForm(): array
    {
        return [
            TextInput::make('model')
                ->label('Modèle IA')
                ->helperText('Modèle Prism à utiliser pour la traduction')
                ->visible(fn($get) => in_array($get('mode'), ['actif', 'test'])),

            TextInput::make('regex_code')
                ->label('Code de déclenchement')
                ->helperText('Code qui doit être présent dans l\'email pour déclencher la traduction')
                ->visible(fn($get) => in_array($get('mode'), ['actif', 'test'])),
        ];
    }

    public static function getInfoList(): array
    {
        return [
            TextEntry::make('model')
                ->label('Modèle IA')
                ->copyable()
                ->copyMessage('Modèle copié!')
                ->icon('heroicon-o-cpu-chip'),

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
        ];
    }

    /**
     * Phase 1: Action spécifique avant mise en queue
     * Marque le draft comme en cours de traitement
     */
    protected function actionDraftBeforeQueue(): ProcessorStatus
    {
        // Marquer le draft comme en cours de traitement
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
            $mode        = $this->getResult('mode', 'inactif');
            $codeOptions = $this->getResult('code_options', []);
            $lang        = array_key_first($codeOptions) ?: 'xx';
            $createNew   = (bool)($codeOptions['n'] ?? false); // [n] = créer nouveau brouillon
            $model       = $this->getServiceOption('model', static::getDefaults()['model']);

            $clean  = $this->removeRegexKeyAndLineIfEmptyHTML($this->emailData->bodyHtml);

            if ($mode === 'test') {
                $translated = app(PrismTextService::class)->translateHtml($clean, $lang, $model);
                
                // ✅ Stocker les résultats AVANT finishProcessor
                $this->setResult('target_language', $lang);
                $this->setResult('translated_content', $translated);
                $this->setResult('processing_mode', 'test');
                $this->setResult('create_new_draft', $createNew);
                
                $this->finishProcessor(
                    ProcessorStatus::Success,
                    "Traduction testée vers {$lang}"
                );
                return $this->email;
            }

            $translated = app(PrismTextService::class)->translateHtml($clean, $lang, $model);

            if ($createNew) {
                // Option [n] : Créer un nouveau brouillon
                $newEmailData = $this->emailData->with(['bodyHtml' => $translated]);
                $resp = $this->emailClient->createDraft($this->user, $newEmailData);

                // Marquer le draft original comme terminé
                $this->markDraftAsCompleted();

                // ✅ Stocker les résultats AVANT finishProcessor
                $this->setResult('new_draft_id', $resp['id'] ?? null);
                $this->setResult('target_language', $lang);
                $this->setResult('translated_content', $translated);
                $this->setResult('processing_mode', 'new_draft');
                
                $this->finishProcessor(
                    ProcessorStatus::Success,
                    "Nouveau brouillon traduit vers {$lang} créé"
                );
            } else {
                // Par défaut : Update du brouillon existant
                $this->updateBody($translated);

                // Marquer le draft comme terminé
                $this->markDraftAsCompleted();

                // ✅ Stocker les résultats AVANT finishProcessor
                $this->setResult('target_language', $lang);
                $this->setResult('translated_content', $translated);
                $this->setResult('processing_mode', 'update');
                
                $this->finishProcessor(
                    ProcessorStatus::Success,
                    "Email traduit vers {$lang} et mis à jour"
                );
            }
        } catch (Exception $e) {
            $this->finishProcessor(ProcessorStatus::Error, $e->getMessage());
        }

        return $this->email;
    }
}
