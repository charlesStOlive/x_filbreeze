<?php

namespace App\Services\Pdf\Templates\Quote;

use Filament\Forms;
use App\Models\Quote;
use Illuminate\Support\Facades\Auth;
use App\Services\Pdf\Base\BasePdfTemplate;

class QuoteBasePdfTemplate extends BasePdfTemplate
{
    public function __construct(protected Quote $quote) {}

    public static function key(): string
    {
        return 'quote_base_pdf';
    }

    public static function label(): string
    {
        return 'Quote Base';
    }

    public function getView(): string
    {
        return 'pdf.quote.base';
    }

    public function getFileName(array $options = []): string
    {
        return $this->quote->code ?? 'Quote#?';
    }

    public function getData(array $options = []): array
    {
        $options = array_merge(static::getDefaultOptions(), $options);

        return [
            'quote' => $this->quote,
            'user' => Auth::user(),
            'options' => $options,
        ];
    }

    public static function getDefaultOptions(): array
    {
        return [
            'avoid_break' => true,
            'avoid_amount_break' => true,
        ];
    }

    public function getForm(): array
    {
        return [

            
            Forms\Components\Checkbox::make('avoid_break')
                ->label('Empêcher les sauts de page dans une cellule')
                ->default(true)
                ->live(),
            Forms\Components\Checkbox::make('avoid_amount_break')
                ->label('Empêcher les sauts de page dans une cellule')
                ->default(true)
                ->live(),
        ];
    }
}
