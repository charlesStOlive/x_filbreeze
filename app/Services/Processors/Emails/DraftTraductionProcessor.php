<?php

namespace App\Services\Processors\Emails;

use Exception;
use CharlesStOlive\MsGraphFilament\Models\MsgEmailDraft;
use CharlesStOlive\MsGraphFilament\Enums\ProcessorStatus;
use CharlesStOlive\MsGraphFilament\Processors\BaseEmailDraftProcessor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;

/**
 * Processeur de brouillons : DraftTraductionProcessor
 * Traduit le contenu d'un brouillon via un service IA
 * 
 * Template Method Pattern :
 * - actionDraftBeforeQueue() : Action avant mise en queue
 * - perform() : Action durant la queue (appel IA + mise à jour)
 */
class DraftTraductionProcessor extends BaseEmailDraftProcessor
{
    // --- MÉTADONNÉES OBLIGATOIRES ---

    public static function getKey(): string
    {
        return 'draft-traduction';
    }

    public static function getIcon(): string
    {
        return 'heroicon-o-language';
    }

    public static function getLabel(): string
    {
        return 'Traduction de brouillon';
    }

    public static function getDescription(): string
    {
        return 'Traduit le contenu du brouillon via un agent IA';
    }

    public static function getDefaultTriggerCode(): string
    {
        return 'trad';
    }

    // --- CONFIGURATION ---

    public static function getDefaults(): array
    {
        return [
            'mode' => 'inactif',
            'regex_code' => 'trad',
            'target_language' => 'en',
            'agent_id' => 'default_translation_agent',
        ];
    }

    // --- INTERFACE FILAMENT ---

    public static function getForm(): array
    {
        return [
            TextInput::make('regex_code')
                ->label('Code de déclenchement')
                ->helperText('Code qui doit être présent dans le brouillon (ex: ## trad ##)')
                ->default('trad')
                ->required()
                ->visible(fn($get) => in_array($get('mode'), ['actif', 'test'])),

            TextInput::make('target_language')
                ->label('Langue cible')
                ->helperText('Code de langue (ex: en, fr, es)')
                ->default('en')
                ->required()
                ->visible(fn($get) => in_array($get('mode'), ['actif', 'test'])),

            TextInput::make('agent_id')
                ->label('ID de l\'agent IA')
                ->helperText('Identifiant de l\'agent de traduction')
                ->default('default_translation_agent')
                ->required()
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
                ->color('primary')
                ->icon('heroicon-o-hashtag'),

            TextEntry::make('target_language')
                ->label('Langue cible')
                ->badge()
                ->icon('heroicon-o-language'),

            TextEntry::make('agent_id')
                ->label('Agent IA')
                ->icon('heroicon-o-cpu-chip'),
        ];
    }

    public static function getResultsInfoList(): array
    {
        return [
            TextEntry::make('target_language')
                ->label('Langue traduite')
                ->badge()
                ->visible(fn($state) => !empty($state)),

            TextEntry::make('processing_mode')
                ->label('Mode')
                ->badge()
                ->color(fn($state) => $state === 'test' ? 'info' : 'success')
                ->visible(fn($state) => !empty($state)),

            TextEntry::make('agent_used')
                ->label('Agent utilisé')
                ->icon('heroicon-o-cpu-chip')
                ->visible(fn($state) => !empty($state)),
        ];
    }

    // --- LOGIQUE TRAITEMENT ---

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
     * Appelle le service IA pour traduire le contenu
     */
    protected function perform(): MsgEmailDraft
    {
        try {
            $targetLanguage = $this->getServiceOption('target_language', 'en');
            $agentId = $this->getServiceOption('agent_id', 'default_translation_agent');

            // Récupérer le contenu actuel et supprimer le code de déclenchement
            $clean = $this->removeRegexKeyAndLineIfEmptyHTML($this->emailData->bodyHtml);

            // Mode test - simulation uniquement
            if ($this->isTestMode()) {
                // ✅ Stocker les résultats AVANT finishProcessor
                $this->setResult('target_language', $targetLanguage);
                $this->setResult('processing_mode', 'test');
                $this->setResult('agent_used', $agentId);
                
                $this->finishProcessor(
                    ProcessorStatus::Success,
                    '[TEST] Simulation réussie - traduction serait effectuée'
                );
                return $this->email;
            }

            // Mode actif - appeler le service IA
            // TODO: Remplacer par votre vrai service IA
            // $translated = app(YourTranslationService::class)->translate($clean, $targetLanguage, $agentId);
            $translated = $clean . "\n\n[Traduction simulée vers {$targetLanguage}]";

            // Mettre à jour le brouillon
            $this->updateBody($translated);

            // Marquer le draft comme terminé (catégorie WORKING_END)
            $this->markDraftAsCompleted();

            // ✅ Stocker les résultats AVANT finishProcessor
            $this->setResult('target_language', $targetLanguage);
            $this->setResult('processing_mode', 'actif');
            $this->setResult('agent_used', $agentId);

            $this->finishProcessor(
                ProcessorStatus::Success,
                'Traduction effectuée avec succès'
            );
        } catch (Exception $e) {
            $this->finishProcessor(ProcessorStatus::Error, $e->getMessage());
        }

        return $this->email;
    }
}
