<?php

namespace App\Services\MsGraph\EmailDraft\Templates\Invoice;

use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use Filament\Forms; // Important pour l'autocompletion des champs
use App\Services\MsGraph\EmailDraft\Templates\Contracts\EmailDraftTemplate;

class InvoiceSummaryTemplate implements EmailDraftTemplate
{
    public static function key(): string
    {
        return 'invoice_summary';
    }
    public static function label(): string
    {
        return 'Résumé facture (contact + montant)';
    }

    public function __construct(protected Invoice $invoice) {}

    public function getView(): string
    {
        return 'emails.drafts.invoice.summary';
    }

    public static function getDefaultOptions(): array
    {
        return [
            'show_intro' => true,
            'show_tva' => true,
        ];
    }

    public static function getForm(array $defaults = []): array
    {
        return [
            Forms\Components\Toggle::make('show_intro')
                ->label('Afficher intro et description')
                ->default($defaults['show_intro'] ?? true)
                ->live(),

            Forms\Components\Toggle::make('show_tva')
                ->label('Afficher la TVA')
                ->default($defaults['show_tva'] ?? false)
                ->live(),
        ];
    }

    public function getData(array $options = []): array
    {
        $options = array_merge(static::getDefaultOptions(), $options);

        return [
            'invoice' => $this->invoice,
            'client' => $this->invoice->client,
            'contact' => $this->invoice->contact,
            'user' => Auth::user(),
            'options' => $options,
        ];
    }

    public function getSubject(): string
    {
        return "Résumé facture #{$this->invoice->invoice_number}";
    }
}
