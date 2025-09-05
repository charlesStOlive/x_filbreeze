<?php

namespace App\Services\Pdf\Templates\Invoice;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Forms;
use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use App\Services\Pdf\Base\BasePdfTemplate;



class InvoiceBasePdfTemplate extends BasePdfTemplate
{
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
        return $this->getRecord()->title ?? 'Invoice ';
    }

    public function getData(array $options = []): array
    {
        $mergedOptions = $this->getMergedOptions($options);

        return [
            'invoice' => $this->getRecord(),
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

    public function getForm(): array
    {
        return [
            Checkbox::make('avoid_break')
                ->label('Empêcher les sauts de page dans une cellule')
                ->default($this->getOption('avoid_break'))
                ->live(),
            TextInput::make('nb_rows')
                ->label('Nombre de lignes de tests')
                ->default($this->getOption('nb_rows'))
                ->integer()
                ->live(),
        ];
    }
}
