<?php

namespace App\Services\Processors\Emails;

use CharlesStOlive\MsGraphFilament\Support\PreflightResult;
use CharlesStOlive\MsGraphFilament\Enums\ProcessorStatus;
use CharlesStOlive\MsGraphFilament\Processors\BaseEmailInProcessor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;

/**
 * Processeur d'emails entrants : DraftTraductionProcessor
 * 
 * Template Method Pattern :
 * - validateBeforeQueue() : Validation avant mise en queue (guardAndCaptureCode auto)
 * - perform() : Traitement en queue (beginProcessor auto)
 */
class DraftTraductionProcessor extends BaseEmailInProcessor
{
    // --- MÉTADONNÉES OBLIGATOIRES ---
    
    public static function getKey(): string
    {
        return 'draft-traduction';
    }

    public static function getIcon(): string
    {
        return 'heroicon-o-envelope';
    }

    public static function getLabel(): string
    {
        return 'Mon Processeur Email';
    }

    public static function getDescription(): string
    {
        return 'Description de mon processeur';
    }

    public static function getDefaultTriggerCode(): string
    {
        return ''; // Vide = pas de code requis
    }

    // --- CONFIGURATION ---
    
    public static function supportsQueue(): bool
    {
        return false;
    }

    public static function requiresRegex(): bool
    {
        return false;
    }

    public static function getDefaults(): array
    {
        return [
            'mode' => 'inactif',
            'my_option' => 'default_value',
        ];
    }

    // --- INTERFACE FILAMENT ---
    
    public static function getForm(): array
    {
        return [
            TextInput::make('my_option')
                ->label('Mon option')
                ->helperText('Description de l\'option')
                ->required()
                ->visible(fn($get) => in_array($get('mode'), ['actif', 'test'])),
                
            Toggle::make('enable_feature')
                ->label('Activer une fonctionnalité')
                ->default(true)
                ->visible(fn($get) => in_array($get('mode'), ['actif', 'test'])),
        ];
    }

    public static function getInfoList(): array
    {
        return [
            TextEntry::make('my_option')
                ->label('Mon option')
                ->icon('heroicon-o-cog'),
                
            TextEntry::make('enable_feature')
                ->label('Fonctionnalité')
                ->formatStateUsing(fn($state) => $state ? 'Activée' : 'Désactivée'),
        ];
    }

    public static function getResultsInfoList(): array
    {
        return [
            TextEntry::make('processing_result')
                ->label('Résultat du traitement')
                ->visible(fn($state) => !empty($state)),
                
            TextEntry::make('processing_mode')
                ->label('Mode')
                ->badge()
                ->color(fn($state) => $state === 'test' ? 'info' : 'success')
                ->visible(fn($state) => !empty($state)),
        ];
    }

    // --- LOGIQUE TRAITEMENT ---

    /**
     * Phase 1: Validation avant mise en queue
     * 
     * ✅ guardAndCaptureCode() appelé automatiquement AVANT
     * ✅ Gestion d'erreurs automatique
     * 
     * Votre rôle : Valider rapidement si l'email doit être traité
     */
    protected function validateBeforeQueue(): PreflightResult
    {
        // Exemple : Vérifier le sujet
        $subject = $this->emailData->subject ?? '';
        
        $this->setResult('subject_value', $subject);
        
        // Exemple de condition de blocage
        if (empty($subject)) {
            $this->updateProcessorStatus(
                ProcessorStatus::Blocked,
                'Le sujet est vide'
            );
            $this->email->save();
            return PreflightResult::blocked('Sujet vide');
        }

        // Validation OK, continuer vers la queue
        return PreflightResult::ok();
    }

    /**
     * Phase 2: Traitement (pas de queue)
     * 
     * Note : Le traitement se fait directement dans validateBeforeQueue()
     * Cette méthode n'est pas utilisée car supportsQueue() = false
     */
    protected function perform(): void
    {
        // Non utilisé car supportsQueue() = false
        // Tout le traitement se fait dans validateBeforeQueue()
    }
}
