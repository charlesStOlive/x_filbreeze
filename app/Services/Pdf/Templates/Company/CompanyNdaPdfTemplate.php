<?php

namespace App\Services\Pdf\Templates\Company;

use Filament\Forms\Components\Select;
use Filament\Forms;
use App\Models\Company;
use App\Models\Contact;
use Illuminate\Support\Facades\Auth;
use App\Services\Pdf\Base\BasePdfTemplate;

class CompanyNdaPdfTemplate extends BasePdfTemplate
{
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
        $clientSlug = $this->getRecord()->slug;
        return 'accord_confidentialite_' . $clientSlug;

    }

    public function getData(array $options = []): array
    {
        $mergedOptions = array_merge(static::getDefaultOptions(), $options);

        if(!isset($options['contact_id'])) {
            $mergedOptions['contact_id'] = $this->getRecord()->contacts()->first()?->id;
        }

        return [
            'company' => $this->getRecord(),
            'user' => Auth::user(),
            'options' => $mergedOptions,
            'contact' => Contact::find($mergedOptions['contact_id'] ?? null),
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
            Select::make('contact_id')
                ->label('Contact de référence')
                ->options(
                    $this->getRecord()->contacts()?->pluck('full_name', 'id')->toArray() ?? []
                )
                ->searchable()
                ->preload()
                ->default($this->getRecord()->contacts()->first()?->id)
                ->live()
        ];
    }
}
