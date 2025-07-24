<?php

namespace App\Services\MsGraph\EmailDraft\Templates\Invoice;

use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use App\Services\MsGraph\EmailDraft\Base\HasPj;
use App\Services\MsGraph\EmailDraft\Base\BaseDraftEmailTemplate;
use App\Services\Pdf\Templates\Invoice as InvoicePdfTemplate;
use Filament\Forms; // Important pour l'autocompletion des champs

class InvoiceSummaryTemplate extends BaseDraftEmailTemplate implements HasPj
{
    public function __construct(protected Invoice $invoice, ?array $options = null)
    {
        parent::__construct($options); // ajoute cette ligne
    }

    public static function key(): string
    {
        return 'invoice_summary';
    }
    public static function label(): string
    {
        return 'Résumé facture (contact + montant)';
    }

    protected function getRecord(): mixed
    {
        return $this->invoice;
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
            InvoicePdfTemplate\InvoiceComplete::class => false,  // pré-coché
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
        $merged = array_merge(
            static::getDefaultOptions(),
            $this->options ?? [],
            $options
        );

        return [
            'invoice' => $this->invoice,
            'client' => $this->invoice->client,
            'contact' => $this->invoice->contact,
            'user' => Auth::user(),
            'options' => $merged,
        ];
    }

    public function getSubject(array $options = []): string
    {
        $merged = array_merge(
            static::getDefaultOptions(),
            $this->options ?? [],
            $options
        );

        return "Résumé facture #{$this->invoice->invoice_number}";
    }
}
