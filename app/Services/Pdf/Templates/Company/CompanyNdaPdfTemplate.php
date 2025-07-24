<?php

namespace App\Services\Pdf\Templates\Company;

use Filament\Forms;
use App\Models\Company;
use App\Models\Contact;
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
        $client = $this->company->slug;
        return 'accord_confidentialite_' . $client;

    }

    public function getData(array $options = []): array
    {
        $options = array_merge(static::getDefaultOptions(), $options);

        if(!isset($options['contact_id'])) {
            $options['contact_id'] = $this->company->contacts()->first()?->id;
        }

        return [
            'company' => $this->company,
            'user' => Auth::user(),
            'options' => $options,
            'contact' => Contact::find($options['contact_id'] ?? null),
        ];
    }

    public static function getDefaultOptions(): array
    {
        return [
            'contact_id' => null,
        ];
    }

    public function getForm(): array
    {
        return [
            Forms\Components\Select::make('contact_id')
                ->label('Contact de référence')
                ->options(
                    $this->company->contacts()?->pluck('full_name', 'id')->toArray() ?? []
                )
                ->searchable()
                ->preload()
                ->default($this->company->contacts()->first()?->id)
                ->live()
        ];
    }
}
