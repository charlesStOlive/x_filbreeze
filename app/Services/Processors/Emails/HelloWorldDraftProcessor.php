<?php

namespace App\Services\Processors\Emails;

use Exception;
use CharlesStOlive\MsGraphFilament\Models\MsgEmailDraft;
use CharlesStOlive\MsGraphFilament\Support\PreflightResult;
use CharlesStOlive\MsGraphFilament\Enums\ProcessorStatus;
use CharlesStOlive\MsGraphFilament\Processors\BaseEmailDraftProcessor;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;

/**
 * Processeur de test simple pour les brouillons
 * Vérifie si le sujet contient "Hello World" et ajoute un texte dans le corps
 */
class HelloWorldDraftProcessor extends BaseEmailDraftProcessor
{
    // --- MÉTADONNÉES OBLIGATOIRES ---
    public static function getKey(): string
    {
        return 'hello-world-draft';
    }

    public static function getIcon(): string
    {
        return 'heroicon-o-hand-raised';
    }

    public static function getLabel(): string
    {
        return 'Hello World Draft !';
    }

    public static function getDescription(): string
    {
        return 'Processeur de test : vérifie "Hello World" dans le sujet et ajoute du texte';
    }

    public static function getDefaultTriggerCode(): string
    {
        return 'hello';
    }

    // --- CONFIGURATION ---
    public static function getDefaults(): array
    {
        return [
            'mode' => 'inactif',
            'regex_code' => 'hello',
            'insert_text' => '✅ Traité par Hello World Draft Processor',
            'create_new_draft' => false,
        ];
    }

    // --- INTERFACE FILAMENT ---
    public static function getForm(): array
    {
        return [
            TextInput::make('regex_code')
                ->label('Code de déclenchement')
                ->helperText('Code qui doit être présent dans le brouillon (ex: ## hello ##)')
                ->default('hello')
                ->required()
                ->visible(fn($get) => in_array($get('mode'), ['actif', 'test'])),

            TextInput::make('insert_text')
                ->label('Texte à insérer')
                ->helperText('Ce texte sera inséré dans le brouillon')
                ->default('✅ Traité par Hello World Draft Processor')
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

            TextEntry::make('insert_text')
                ->label('Texte à insérer')
                ->icon('heroicon-o-document-text'),
        ];
    }

    public static function getResultsInfoList(): array
    {
        return [
            TextEntry::make('subject_value')
                ->label('Sujet du brouillon')
                ->visible(fn($state) => !empty($state)),

            TextEntry::make('processing_mode')
                ->label('Mode de traitement')
                ->badge()
                ->color(fn($state) => $state === 'test' ? 'info' : 'success')
                ->visible(fn($state) => !empty($state)),

            TextEntry::make('text_inserted')
                ->label('Texte inséré')
                ->visible(fn($state) => !empty($state)),
        ];
    }

    // --- LOGIQUE TRAITEMENT ---

    /**
     * Phase 1: Validation avant mise en queue
     * Vérifie que le sujet contient "Hello World"
     */
    protected function validateDraftBeforeQueue(): PreflightResult
    {
        // Validation OK : marquer le draft comme en cours de traitement
        $this->markDraftAsProcessing();
        return PreflightResult::ok();
    }

    /**
     * Phase 2: Traitement en queue
     * Insère le texte configuré dans le brouillon
     */
    protected function perform(): MsgEmailDraft
    {
        try {
            $insertText = $this->getServiceOption('insert_text', '✅ Traité par Hello World Draft Processor');

            // Récupérer le contenu actuel et supprimer le code de déclenchement
            $clean = $this->removeRegexKeyAndLineIfEmptyHTML($this->emailData->bodyHtml);

            // Ajouter notre texte au début
            $newContent = "<p><strong>{$insertText}</strong></p>" . $clean;

            // Mode test - simulation uniquement
            if ($this->isTestMode()) {
                $this->finishProcessor(ProcessorStatus::Success, [
                    'text_inserted' => $insertText,
                    'processing_mode' => 'test',
                ], '[TEST] Simulation réussie - texte serait inséré');
                return $this->email;
            }

            // Mode actif - mettre à jour le brouillon
            $this->updateBody($newContent);

            // Marquer le draft comme terminé
            $this->markDraftAsCompleted();

            $this->finishProcessor(ProcessorStatus::Success, [
                'text_inserted' => $insertText,
                'processing_mode' => 'actif',
            ], 'Texte inséré avec succès dans le brouillon');
        } catch (Exception $e) {
            $this->finishProcessor(ProcessorStatus::Error, [], $e->getMessage());
        }

        return $this->email;
    }
}
