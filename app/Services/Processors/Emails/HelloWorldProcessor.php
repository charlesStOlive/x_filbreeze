<?php

namespace App\Services\Processors\Emails;

use Exception;
use CharlesStOlive\MsGraphFilament\Support\PreflightResult;
use CharlesStOlive\MsGraphFilament\Enums\ProcessorStatus;
use CharlesStOlive\MsGraphFilament\Processors\BaseEmailInProcessor;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;

/**
 * Processeur simple pour vérifier "Hello World" dans le sujet et une phrase dans le contenu
 */
class HelloWorldProcessor extends BaseEmailInProcessor
{
    // --- MÉTADONNÉES OBLIGATOIRES ---
    public static function getKey(): string
    {
        return 'hello-world';
    }

    public static function getIcon(): string
    {
        return 'heroicon-o-hand-raised';
    }

    public static function getLabel(): string
    {
        return 'Hello World !';
    }

    public static function getDescription(): string
    {
        return 'Vérifie si le sujet contient "Hello World" et recherche une phrase dans le contenu';
    }

    public static function getDefaultTriggerCode(): string
    {
        return ''; // Pas de code de déclenchement requis
    }

    // --- CONFIGURATION ---
    public static function supportsQueue(): bool
    {
        return true; // Utilise la queue pour le traitement
    }

    public static function requiresRegex(): bool
    {
        return false; // Pas de regex requis, traitement automatique
    }

    public static function getDefaults(): array
    {
        return [
            'mode' => 'inactif',
            'search_phrase' => 'mon texte',
        ];
    }

    // --- INTERFACE FILAMENT ---
    public static function getForm(): array
    {
        return [
            TextInput::make('search_phrase')
                ->label('Phrase à rechercher')
                ->helperText('Cette phrase sera recherchée dans le contenu de l\'email')
                ->default('mon texte')
                ->required()
                ->visible(fn($get) => in_array($get('mode'), ['actif', 'test'])),
        ];
    }

    public static function getInfoList(): array
    {
        return [
            TextEntry::make('search_phrase')
                ->label('Phrase recherchée')
                ->icon('heroicon-o-magnifying-glass')
                ->copyable(),
        ];
    }

    public static function getResultsInfoList(): array
    {
        return [
            TextEntry::make('subject_value')
                ->label('Sujet de l\'email')
                ->visible(fn($state) => !empty($state)),
            TextEntry::make('phrase_check')
                ->label('Vérification phrase')
                ->badge()
                ->color(fn($state) => $state === 'Trouvé' ? 'success' : 'danger')
                ->visible(fn($state) => !empty($state)),
            TextEntry::make('processing_mode')
                ->label('Mode de traitement')
                ->badge()
                ->color(fn($state) => $state === 'test' ? 'info' : 'success')
                ->visible(fn($state) => !empty($state)),
            TextEntry::make('content_preview')
                ->label('Aperçu du contenu')
                ->limit(200)
                ->visible(fn($state) => !empty($state)),
        ];
    }

    // --- LOGIQUE TRAITEMENT ---

    /**
     * Phase 1: Vérification avant mise en queue
     * Vérifie que le sujet contient "Hello World"
     */
    protected function validateBeforeQueue(): PreflightResult
    {
        // Vérifier le sujet
        $subject = $this->emailData->subject ?? '';
        $hasHelloWorld = stripos($subject, 'hello world') !== false;

        // Stocker les résultats de la vérification du sujet
        $this->setResult('subject_value', $subject);
        $this->setResult('subject_check', $hasHelloWorld ? 'Oui' : 'Non');

        if (!$hasHelloWorld) {
            $this->updateProcessorStatus(
                ProcessorStatus::Blocked,
                'Le sujet ne contient pas "Hello World"'
            );
            return PreflightResult::blocked('Le sujet ne contient pas "Hello World"');
        }

        // Si la validation passe, on continue vers la queue
        return PreflightResult::ok();
    }

    /**
     * Phase 2: Traitement en queue
     * Recherche de la phrase dans le contenu de l'email
     */
    protected function perform(): void
    {
        $searchPhrase = $this->getServiceOption('search_phrase', 'mon texte');

        // Récupérer le contenu de l'email (texte ou HTML)
        $content = $this->emailData->bodyText ?? strip_tags($this->emailData->bodyHtml ?? '');

        // Stocker un aperçu du contenu
        $this->setResult('content_preview', mb_substr($content, 0, 500));

        // Rechercher la phrase dans le contenu
        $phraseFound = stripos($content, $searchPhrase) !== false;
        $this->setResult('phrase_check', $phraseFound ? 'Trouvé' : 'Absent');

        // Mode actif - traitement réel
        if ($phraseFound) {
            // En mode actif, ajouter la catégorie HW via Microsoft Graph
            if (!$this->isTestMode()) {
                try {
                    $this->emailClient->updateIncomingEmail($this->user, $this->email, [
                        'categories' => ['HW']
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Erreur lors de l\'ajout de la catégorie HW: ' . $e->getMessage());
                }
            }

            $this->finishProcessor(
                ProcessorStatus::Success,
                [
                    'phrase_searched' => $searchPhrase,
                    'phrase_position' => stripos($content, $searchPhrase),
                ],
                "Phrase \"{$searchPhrase}\" trouvée dans le contenu"
            );
        } else {
            $this->finishProcessor(
                ProcessorStatus::Blocked,
                [
                    'phrase_searched' => $searchPhrase,
                ],
                "Phrase \"{$searchPhrase}\" non trouvée dans le contenu"
            );
        }
    }
}
