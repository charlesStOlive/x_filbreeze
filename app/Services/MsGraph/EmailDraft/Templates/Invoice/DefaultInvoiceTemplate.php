<?php

namespace App\Services\MsGraph\EmailDraft\Templates\Invoice;

use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use Filament\Forms;
use App\Services\MsGraph\EmailDraft\Templates\Contracts\EmailDraftTemplate;

class DefaultInvoiceTemplate implements EmailDraftTemplate
{
    public static function key(): string
    {
        return 'invoice_default';
    }
    public static function label(): string
    {
        return 'Facture simple';
    }

    public function __construct(protected Invoice $invoice) {}

    public function getView(): string
    {
        return 'emails.drafts.invoice.default';
    }

    public static function getDefaultOptions(): array
    {
        return [
            'simplified' => false,
        ];
    }

    public static function getForm(array $defaults = []): array
    {
        return [
            Forms\Components\Toggle::make('simplified')
                ->label('Versions simplifié')
                ->default($defaults['simplified'] ?? true)
                ->live(),
        ];
    }

    public function getData(array $options = []): array
    {
        //\Log::info('option avant',$options);


        $options = array_merge(static::getDefaultOptions(), $options);

        //\Log::info('option apres' ,$options);

        return [
            'invoice' => $this->invoice,
            'company' => $this->invoice->company,
            'contact' => $this->invoice->contact,
            'options' => $options,
            'user' => Auth::user(),
        ];
    }

    public function getSubject(): string
    {
        return "Facture #{$this->invoice->code}";
    }
}
