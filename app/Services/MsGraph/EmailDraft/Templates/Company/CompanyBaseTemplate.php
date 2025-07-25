<?php

namespace App\Services\MsGraph\EmailDraft\Templates\Company;

use Filament\Forms;
use Illuminate\Support\Facades\Auth;
use App\Services\MsGraph\EmailDraft\Base\HasPj;
use App\Services\MsGraph\EmailDraft\Base\BaseDraftEmailTemplate;
use App\Services\MaatExports\Templates\Company\CompanyProductsExporter;

class CompanyBaseTemplate extends BaseDraftEmailTemplate implements HasPj
{
    public static function key(): string
    {
        return 'company_base';
    }

    public static function label(): string
    {
        return 'Company Base';
    }

    public function getView(): string
    {
        return 'emails.drafts.company.base';
    }

    public function getToOptions(): array
    {
            return $this->getRecord()->contacts->pluck('email', 'email')->toArray();
    }

    public function getDefaultTo(): array
    {
        $options = $this->getToOptions();
        return count($options) > 0 ? [array_key_first($options)] : [];
    }


    public static function getDefaultOptions(): array
    {
        return [
            'show_intro' => true,
            'show_details' => true,
        ];
    }

    public function getForm(): array
    {
        return [
            Forms\Components\Toggle::make('show_intro')
                ->label("Afficher l'introduction")
                ->default($this->getOption('show_intro'))
                ->live(),

            Forms\Components\Toggle::make('show_details')
                ->label("Afficher les détails")
                ->default($this->getOption('show_details'))
                ->live(),
        ];
    }

    public function getData(array $options = []): array
    {
        $company = $this->getRecord();
        $merged = $this->getMergedOptions($options);

        return [
            'company' => $company,
            'user' => Auth::user(),
            'options' => $merged,
        ];
    }

    public function getSubject(array $options = []): string
    {
        $company = $this->getRecord();
        return "Objet de company ". $company->id;
    }

    public static function getAvailableAttachments(): array
    {
        return [
            CompanyProductsExporter::class => true,
        ];
    }
}
