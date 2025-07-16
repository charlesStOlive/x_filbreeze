<?php  namespace App\Services\MsGraph\EmailDraft\Templates\Company;

use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use App\Services\MsGraph\EmailDraft\Templates\Contracts\EmailDraftTemplate;

class DefaultCompanyTemplate implements EmailDraftTemplate
{
    public static function key(): string { return 'company_default'; }
    public static function label(): string { return 'Liste des contacts (entreprise)'; }

    public function __construct(protected Company $company) {}

    public function getView(): string { return 'emails.drafts.company.default'; }

    public static function getDefaultOptions(): array
    {
        return [];
    }

    public static function getForm(array $defaults = []): array
    {
        return [];
    }



    public function getData(array $options = []): array
    {
        $options = array_merge(static::getDefaultOptions(), $options);

        return [
            'company' => $this->company,
            'contacts' => $this->company->contacts,
            'user' => Auth::user(),
            'options' => $options,
        ];
    }

    public function getSubject(): string {
        return "Contacts de {$this->company->name}";
    }
}