<?php 

namespace App\Services\MsGraph\EmailDraft\Templates\Invoice;

use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use App\Services\MsGraph\EmailDraft\Templates\Contracts\EmailDraftTemplate;

class DefaultInvoiceTemplate implements EmailDraftTemplate
{
    public static function key(): string { return 'invoice_default'; }
    public static function label(): string { return 'Facture par défaut'; }

    public function __construct(protected Invoice $invoice) {}

    public function getView(): string { return 'emails.drafts.invoice.default'; }

    public function getData(): array {
        return [
            'invoice' => $this->invoice,
            'company' => $this->invoice->company,
            'contact' => $this->invoice->contact,
            'user' => Auth::user(),
        ];
    }

    public function getSubject(): string {
        return "Facture #{$this->invoice->invoice_number}";
    }
}