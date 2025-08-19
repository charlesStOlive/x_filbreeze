<?php

namespace App\Services\Pdf\Templates\Invoice;

use Filament\Forms;
use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use App\Services\Pdf\Base\BasePdfTemplate;



class InvoiceSummaryPdfTemplate extends BasePdfTemplate
{
    public static function key(): string
    {
        return 'invoice_summary_pdf';
    }
    public static function label(): string
    {
        return 'Facture PDF (simple)';
    }

    
    public function getView(): string
    {
        return 'pdf.invoice.summary';
    }

    public function getFileName(array $options = []): string
    {
        $code = $this->getRecord()->code ?? 'facture';
        return $code.'_s';
    }

    public function getData(array $options = []): array
    {
        $mergedOptions = array_merge(static::getDefaultOptions(), $options);

        return [
            'invoice' => $this->getRecord(),
            'user' => Auth::user(),
            'options' => $mergedOptions,
        ];
    }

    public static function getDefaultOptions(): array
    {
        return [
            'avoid_full_break' => false,
            'avoid_inside_break' => true,
            'avoid_amount_break' => true,
        ];
    }

    public static function getForm(array $defaults = []): array
    {
        return [
            Forms\Components\Checkbox::make('avoid_full_break')
                ->label('Empêcher les sauts de page dans le tableai (si intro longue et petit tableau)')
                ->default($defaults['avoid_inside_break'] ?? false)
                ->live(),
            Forms\Components\Checkbox::make('avoid_inside_break')
                ->label('Empêcher les sauts de page au milieu d une ligne du tableau principal')
                ->default($defaults['avoid_inside_break'] ?? false)
                ->live(),
            Forms\Components\Checkbox::make('avoid_amount_break')
                ->label('Empêcher les sauts de page au milieu des montants')
                ->default($defaults['avoid_amount_break'] ?? true)
                ->live(),
        ];
    }
}
