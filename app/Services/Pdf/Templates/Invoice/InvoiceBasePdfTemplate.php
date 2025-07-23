<?php

namespace App\Services\Pdf\Templates\Invoice;

use Filament\Forms;
use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use App\Services\Pdf\Base\BasePdfTemplate;



class InvoiceBasePdfTemplate extends BasePdfTemplate
{
    public function __construct(protected Invoice $invoice) {}

    public static function key(): string
    {
        return 'invoice_base_pdf';
    }
    public static function label(): string
    {
        return 'Invoice Base';
    }


    public function getView(): string
    {
        return 'pdf.invoice.base';
    }

    public function getFileName(array $options = []): string
    {
        return $this->invoice->title ?? 'Invoice ';
    }

    public function getData(array $options = []): array
    {
        $options = array_merge(static::getDefaultOptions(), $options);

        return [
            'invoice' => $this->invoice,
            'user' => Auth::user(),
            'options' => $options,
        ];
    }

    public static function getDefaultOptions(): array
    {
        return [
            'avoid_break' => true,
            'nb_rows' => 20,
            // 'option' => false,
        ];
    }

    public static function getForm(array $defaults = []): array
    {
        \Log::info('getForm called with defaults: ', $defaults);
        return [
            Forms\Components\Checkbox::make('avoid_break')
                ->label('Empêcher les sauts de page dans une cellule')
                ->default(true)
                ->live(),
            Forms\Components\TextInput::make('nb_rows')
                ->label('Nombre de lignes de tests')
                ->default(20)
                ->integer()
                ->live(),
            // Forms\Components\Checkbox::make('option')
            //     ->label('option_1 label')
            //     ->default($defaults['option_1'] ?? false)
            //     ->live(),
        ];
    }
}
