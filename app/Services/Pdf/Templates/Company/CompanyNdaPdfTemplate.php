<?php

namespace App\Services\Pdf\Templates\Company;

use Filament\Forms;
use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use App\Services\Pdf\Base\BasePdfTemplate;

class CompanyNdaPdfTemplate extends BasePdfTemplate
{
    public function __construct(protected Company $company) {}

    public static function key(): string
    {
        return 'company_nda_pdf';
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
        return $this->company->title ?? 'Company';
    }

    public function getData(array $options = []): array
    {
        $options = array_merge(static::getDefaultOptions(), $options);

        return [
            'company' => $this->company,
            'user' => Auth::user(),
            'options' => $options,
        ];
    }

    public static function getDefaultOptions(): array
    {
        return [
            'avoid_break' => true,
            'nb_rows' => 20,
        ];
    }

    public static function getForm(array $defaults = []): array
    {
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
        ];
    }
}
