<?php

namespace App\Services\Pdf\Templates\Invoice;

use Filament\Forms;
use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use App\Services\Pdf\Base\BasePdfTemplate;

class InvoiceNdaPdfTemplate extends BasePdfTemplate
{
    public static function key(): string
    {
        return 'invoice_nda_pdf';
    }

    public static function label(): string
    {
        return 'Accord de confidentialité';
    }

    public function getView(): string
    {
        return 'pdf.company.nda';
    }

    public function getFileName(array $options = []): string
    {
        return $this->getRecord()->title ?? 'Invoice';
    }

    public function getData(array $options = []): array
    {
        $mergedOptions = array_merge(static::getDefaultOptions(), $options);

        return [
            'company' => $this->getRecord()->company,
            'user' => Auth::user(),
            'options' => $mergedOptions,
        ];
    }

    public static function getDefaultOptions(): array
    {
        return [
            'avoid_break' => true,
            'nb_rows' => 20,
        ];
    }

    public function getForm(array $defaults = []): array
    {
        return [
            Forms\Components\Checkbox::make('avoid_break')
                ->label('Empêcher les sauts de page dans une cellule')
                ->default($this->getOption('avoid_break'))
                ->live(),

            Forms\Components\TextInput::make('nb_rows')
                ->label('Nombre de lignes de tests')
                ->default($this->getOption('nb_rows'))
                ->integer()
                ->live(),
        ];
    }
}
