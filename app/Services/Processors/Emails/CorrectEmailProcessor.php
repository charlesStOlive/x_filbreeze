<?php

namespace App\Services\Processors\Emails;

use CharlesStOlive\FilamentPrism\Models\AiInteraction;
use CharlesStOlive\FilamentPrism\Registry\AiTaskRegistry;
use CharlesStOlive\FilamentPrism\Services\AiRunner;
use CharlesStOlive\FilamentPrism\Support\CorrectableField;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;

/**
 * Corrige l'orthographe et la grammaire d'un brouillon Outlook qui commence par
 * `## corrige ##` (voir `AiDraftProcessor` pour -n, le mode test et l'historique conservé).
 *
 * Pas de ressource IA propre : c'est la correction orthographique de filament-prism
 * (`orthography`, la même que pour les devis et factures), à qui le message est confié comme
 * un champ HTML à corriger. Son modèle est le modèle par défaut (`AI_MODEL`).
 */
class CorrectEmailProcessor extends AiDraftProcessor
{
    /** Le nom du champ envoyé à la correction ; son libellé aide l'IA à situer le texte. */
    private const FIELD = 'message';

    public static function getKey(): string
    {
        return 'd-cor';
    }
    public static function getIcon(): string
    {
        return 'heroicon-o-pencil-square';
    }
    public static function getLabel(): string
    {
        return 'Corriger le texte';
    }
    public static function getDescription(): string
    {
        return 'Corrige l\'orthographe et la grammaire du mail (## corrige ##)';
    }
    public static function getDefaultTriggerCode(): string
    {
        return 'corrige';
    }

    public static function getDefaults(): array
    {
        return [
            'mode' => 'inactif',
            'regex_code' => 'corrige',
        ];
    }

    // --- UI pour ton builder ---
    public static function getForm(): array
    {
        return [
            TextInput::make('regex_code')
                ->label('Code de déclenchement')
                ->helperText('Code qui doit être présent dans l\'email pour déclencher la correction')
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
        return static::commonResultsInfoList('Texte corrigé');
    }

    protected function runAi(string $message): AiInteraction
    {
        return app(AiRunner::class)->run(
            app(AiTaskRegistry::class)->resource('orthography'),
            input: [self::FIELD => $message],
            context: ['fields' => [CorrectableField::make(self::FIELD)->label('Message d\'un email')->html()]],
            attachTo: $this->email,
        );
    }

    protected function outputHtml(AiInteraction $interaction): string
    {
        return $interaction->output[self::FIELD] ?? '';
    }

    protected function successMessage(string $processingMode): string
    {
        return match ($processingMode) {
            'test'      => 'Correction testée',
            'new_draft' => 'Nouveau brouillon corrigé créé',
            default     => 'Email corrigé et mis à jour',
        };
    }
}
