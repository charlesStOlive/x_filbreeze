<?php

namespace App\Services\Pdf\Templates\Quote;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Toggle;
use App\Models\Quote;
use Illuminate\Support\Facades\Auth;
use App\Services\Pdf\Base\BasePdfTemplate;

class QuoteDetailedPdfTemplate extends BasePdfTemplate
{
    public static function key(): string
    {
        return 'quote_detailed_pdf';
    }

    public static function label(): string
    {
        return 'Devis détaillé';
    }

    public function getView(): string
    {
        return 'pdf.quote.detailed';
    }

    public function getFileName(array $options = []): string
    {
        $code = $this->getRecord()->code ?? 'devis';
        return $code . '_detailed';
    }

    public function getData(array $options = []): array
    {
        $mergedOptions = $this->getMergedOptions($options);

        return [
            'quote' => $this->getRecord(),
            'user' => Auth::user(),
            'options' => $mergedOptions,
        ];
    }

    public static function getDefaultOptions(): array
    {
        return [
            'show_description' => true,
            'show_timeline' => true,
            'avoid_break' => true,
            'avoid_amount_break' => true,
        ];
    }

    public static function getForm(array $defaults = []): array
    {
        return [
            Toggle::make('show_description')
                ->label('Afficher les descriptions détaillées')
                ->default($defaults['show_description'] ?? true),

            Toggle::make('show_timeline')
                ->label('Afficher la timeline du projet')
                ->default($defaults['show_timeline'] ?? true),

            Checkbox::make('avoid_break')
                ->label('Éviter les sauts de page dans le tableau')
                ->default($defaults['avoid_break'] ?? true),

            Checkbox::make('avoid_amount_break')
                ->label('Empêcher les sauts de page dans les montants')
                ->default($defaults['avoid_amount_break'] ?? true),
        ];
    }
}
