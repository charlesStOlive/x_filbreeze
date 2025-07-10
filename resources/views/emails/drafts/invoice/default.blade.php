<h1>Facture #{{ $invoice->code }}</h1>

<p><strong>Client :</strong> {{ $company->title }}</p>
<p><strong>Contact :</strong> {{ $contact->full_name }} ({{ $contact->email }})</p>

<p><strong>Date :</strong> {{ $invoice->invoice_at_my?->format('d/m/Y') }}</p>
<p><strong>Total TTC :</strong> {{ number_format($invoice->total_ttc, 2, ',', ' ') }} €</p>

<hr>


