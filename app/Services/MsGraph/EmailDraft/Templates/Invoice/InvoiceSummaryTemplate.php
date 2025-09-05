<?php

namespace App\Services\MsGraph\EmailDraft\Templates\Invoice;


use Filament\Forms\Components\Toggle;
use Illuminate\Support\Facades\Auth;
use App\Services\MsGraph\EmailDraft\Base\HasPj;
use App\Services\MsGraph\EmailDraft\Base\BaseDraftEmailTemplate;
use App\Services\Pdf\Templates\Invoice as InvoicePdfTemplate;
use Filament\Forms; // Important pour l'autocompletion des champs

class InvoiceSummaryTemplate extends BaseDraftEmailTemplate implements HasPj
{
    public static function key(): string
    {
        return 'invoice_summary';
    }

    public static function label(): string
    {
        return 'Résumé facture (contact + montant)';
    }

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

    public static function getAvailableAttachments(): array
    {
        return [
            InvoicePdfTemplate\InvoiceSummaryPdfTemplate::class => true,
            InvoicePdfTemplate\InvoiceComplete::class => false,
        ];
    }

    public function getToOptions(): array
    {
        return  $this->getRecord()->company->contacts->pluck('email', 'email')->toArray();; // plus besoin de propriété

    }

    public function getDefaultTo(): array
    {
        return  $this->getRecord()->contact?->email ? [$this->getRecord()->contact->email] : [];
    }

    public function getForm(): array
    {
        return [
            Toggle::make('show_intro')
                ->label('Afficher intro et description')
                ->default($this->getOption('show_intro', true))
                ->live(),

            Toggle::make('show_tva')
                ->label('Afficher la TVA')
                ->default($this->getOption('show_tva', false))
                ->live(),
        ];
    }

    public function getData(array $options = []): array
    {
        $invoice = $this->getRecord(); // plus besoin de propriété
        $merged = $this->getMergedOptions($options);

        return [
            'invoice' => $invoice,
            'client' => $invoice->client,
            'contact' => $invoice->contact,
            'user' => Auth::user(),
            'options' => $merged,
        ];
    }

    public function getSubject(array $options = []): string
    {
        $invoice = $this->getRecord();
        return "Résumé facture #{$invoice->code}";
    }
}
