<?php

namespace App\Services\Qonto;

use App\Models\Company;
use App\Models\Invoice;
use Carbon\CarbonInterface;
use CharlesStOlive\FilamentQonto\DTO\ClientData;
use CharlesStOlive\FilamentQonto\DTO\ClientInvoiceData;
use CharlesStOlive\FilamentQonto\Services\BankAccountsService;
use CharlesStOlive\FilamentQonto\Services\ClientInvoicesService;
use CharlesStOlive\FilamentQonto\Services\ClientsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CrmInvoiceQontoService
{
    public function __construct(
        protected ClientsService $clients,
        protected ClientInvoicesService $clientInvoices,
        protected BankAccountsService $bankAccounts,
    ) {}

    public function submit(Invoice $invoice): Invoice
    {
        \Log::info('Submitting invoice', ['invoice_id' => $invoice->id]);
        $invoice->loadMissing(['company', 'contact']);
        \Log::info('Ensuring client for company', ['company_id' => $invoice->company->id]);
        $client = $this->ensureClient($invoice->company, $invoice);

        $payload = $this->invoicePayload($invoice, $client);
        \Log::info('payload', ['payload' => $payload]);

        if ($invoice->qonto_invoice_id) {
            $qontoInvoice = $this->clientInvoices->retrieve($invoice->qonto_invoice_id);

            if ($qontoInvoice->status === 'draft') {
                $qontoInvoice = $this->clientInvoices->updateDraft($invoice->qonto_invoice_id, $payload);
            }
        } else {
            \Log::info('Pas encore soumise ', ['payload' => $payload]);
            $qontoInvoice = $this->clientInvoices->create($payload);
            $this->storeInvoice($invoice, $qontoInvoice);
        }

        \Log::info('Invoice submitted', ['invoice_id' => $invoice->id]);

        $downloaded = $this->clientInvoices->downloadFacturX(
            $invoice->qonto_invoice_id ?: $qontoInvoice->id,
            true,
            ($invoice->code ?: 'facture-' . $invoice->getKey()) . '.pdf',
        );

        /** @var ClientInvoiceData $finalInvoice */
        $finalInvoice = $downloaded['invoice'];
        $this->storeInvoice($invoice, $finalInvoice, $downloaded);

        if ((bool) config('qonto.client_invoices.auto_send_by_einvoice', false)) {
            $this->sendByEinvoice($invoice);
        }

        return $invoice->refresh();
    }

    public function markPaid(Invoice $invoice, CarbonInterface|string|null $paidAt = null): Invoice
    {
        if (! $invoice->qonto_invoice_id) {
            return $invoice;
        }

        $qontoInvoice = $this->clientInvoices->markAsPaid(
            $invoice->qonto_invoice_id,
            $paidAt ? Carbon::parse($paidAt)->toDateString() : null,
        );

        $this->storeInvoice($invoice, $qontoInvoice);

        return $invoice->refresh();
    }

    public function cancel(Invoice $invoice): Invoice
    {
        if (! $invoice->qonto_invoice_id) {
            return $invoice;
        }

        $qontoInvoice = $this->clientInvoices->retrieve($invoice->qonto_invoice_id);

        if ($qontoInvoice->status === 'draft') {
            $this->clientInvoices->deleteDraft($invoice->qonto_invoice_id);

            $invoice->forceFill([
                'qonto_status' => 'deleted',
                'qonto_synced_at' => now(),
            ])->save();

            return $invoice->refresh();
        }

        $qontoInvoice = $this->clientInvoices->markAsCanceled($invoice->qonto_invoice_id);
        $this->storeInvoice($invoice, $qontoInvoice);

        return $invoice->refresh();
    }

    public function sendByEinvoice(Invoice $invoice): Invoice
    {
        if (! $invoice->qonto_invoice_id) {
            throw ValidationException::withMessages([
                'qonto_invoice_id' => 'La facture doit d’abord être créée chez Qonto.',
            ]);
        }

        $invoice->loadMissing('company');

        if ($invoice->company && $invoice->company->qonto_e_invoicing_reachable === false) {
            throw ValidationException::withMessages([
                'company_id' => 'Ce client n’est pas indiqué comme joignable sur le réseau e-invoicing Qonto.',
            ]);
        }

        $this->clientInvoices->sendByEinvoice($invoice->qonto_invoice_id);
        $qontoInvoice = $this->clientInvoices->retrieve($invoice->qonto_invoice_id);
        $this->storeInvoice($invoice, $qontoInvoice);

        return $invoice->refresh();
    }

    protected function ensureClient(?Company $company, Invoice $invoice): ClientData
    {
        if (! $company) {
            throw ValidationException::withMessages([
                'company_id' => 'La facture doit être liée à un client avant l’envoi vers Qonto.',
            ]);
        }

        if ($company->qonto_client_id) {
            \Log::info('Qonto client lookup by stored id', [
                'company_id' => $company->id,
                'qonto_client_id' => $company->qonto_client_id,
            ]);

            $client = $this->clients->retrieve($company->qonto_client_id);
            $this->storeClient($company, $client);

            return $client;
        }

        foreach ($this->taxIdentifierCandidates($company) as $taxIdentifier) {
            \Log::info('Qonto client lookup by tax identifier', [
                'company_id' => $company->id,
                'tax_identifier' => $taxIdentifier,
            ]);

            $client = $this->clients->findByTaxIdentificationNumber($taxIdentifier);

            if ($client) {
                \Log::info('Qonto client found by tax identifier', [
                    'company_id' => $company->id,
                    'qonto_client_id' => $client->id,
                ]);

                $this->storeClient($company, $client);

                return $client;
            }
        }

        $vatNumber = $this->vatNumber($company);

        if ($vatNumber) {
            \Log::info('Qonto client lookup by VAT number', [
                'company_id' => $company->id,
                'vat_number' => $vatNumber,
            ]);

            $client = $this->clients->findByVatNumber($vatNumber);

            if ($client) {
                \Log::info('Qonto client found by VAT number', [
                    'company_id' => $company->id,
                    'qonto_client_id' => $client->id,
                ]);

                $this->storeClient($company, $client);

                return $client;
            }
        }

        $email = $this->clientEmail($company, $invoice);

        if ($email) {
            \Log::info('Qonto client lookup by email fallback', [
                'company_id' => $company->id,
                'email' => $email,
            ]);

            $client = $this->clients->findByEmail($email);

            if ($client) {
                \Log::info('Qonto client found by email fallback', [
                    'company_id' => $company->id,
                    'qonto_client_id' => $client->id,
                ]);

                $this->storeClient($company, $client);

                return $client;
            }
        }

        \Log::info('Qonto client not found, creating client', [
            'company_id' => $company->id,
        ]);

        $client = $this->clients->create($this->clientPayload($company, $invoice));
        $this->storeClient($company, $client);

        \Log::info('Qonto client created', [
            'company_id' => $company->id,
            'qonto_client_id' => $client->id,
        ]);

        return $client;
    }

    protected function taxIdentifierCandidates(Company $company): array
    {
        $countryCode = $this->countryCode($company);
        $candidates = [];

        foreach ([$company->tax_identification_number, $company->siret] as $value) {
            $value = trim((string) $value);

            if ($value === '') {
                continue;
            }

            if ($countryCode === 'FR') {
                $clean = $this->cleanIdentifier($value);

                if (! $clean) {
                    continue;
                }

                if (strlen($clean) >= 9) {
                    $candidates[] = substr($clean, 0, 9);
                }

                if (strlen($clean) > 9) {
                    $candidates[] = $clean;
                }

                continue;
            }

            $candidates[] = $value;
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    protected function preferredTaxIdentifier(Company $company): ?string
    {
        return $this->taxIdentifierCandidates($company)[0] ?? null;
    }

    protected function vatNumber(Company $company): ?string
    {
        $vatNumber = strtoupper(str_replace(' ', '', trim((string) $company->vat_number)));

        return $vatNumber !== '' ? $vatNumber : null;
    }

    protected function clientEmail(Company $company, Invoice $invoice): ?string
    {
        $email = trim((string) ($company->email ?: $invoice->contact?->email));

        return $email !== '' ? $email : null;
    }

    protected function clientPayload(Company $company, Invoice $invoice): array
    {
        $countryCode = $this->countryCode($company);
        $address = trim((string) $company->address);
        $city = trim((string) $company->city);
        $zipCode = trim((string) $company->cp);

        if ($address === '' || $city === '' || $zipCode === '') {
            throw ValidationException::withMessages([
                'company_id' => 'Qonto demande une adresse de facturation complète : adresse, ville et code postal.',
            ]);
        }

        $payload = [
            'kind' => 'company',
            'name' => Str::limit((string) $company->title, 250, ''),
            'email' => $this->clientEmail($company, $invoice),
            'currency' => config('qonto.clients.default_currency', 'EUR'),
            'locale' => config('qonto.clients.default_locale', 'fr'),
            'billing_address' => [
                'street_address' => Str::limit($address, 250, ''),
                'city' => Str::limit($city, 50, ''),
                'zip_code' => Str::limit($zipCode, 20, ''),
                'country_code' => $countryCode,
            ],
        ];

        foreach (
            [
                'vat_number' => $this->vatNumber($company),
                'tax_identification_number' => $this->preferredTaxIdentifier($company),
                'e_invoicing_address' => $company->e_invoicing_address,
            ] as $key => $value
        ) {
            if (filled($value)) {
                $payload[$key] = $value;
            }
        }

        return array_filter($payload, fn($value): bool => $value !== null && $value !== '');
    }

    protected function invoicePayload(Invoice $invoice, ClientData $client): array
    {
        $currency = $client->currency ?: config('qonto.clients.default_currency', 'EUR');
        $issueDate = $this->issueDate($invoice);
        $terms = config('qonto.client_invoices.terms_and_conditions');

        $payload = [
            'client_id' => $client->id,
            'issue_date' => $issueDate->toDateString(),
            'due_date' => $this->dueDate($invoice, $issueDate)->toDateString(),
            'currency' => $currency,
            'status' => 'draft',
            'number' => $invoice->code,
            'payment_methods' => [
                'iban' => $this->paymentIban(),
            ],
            'items' => $this->items($invoice, $currency),
            'header' => $invoice->title,
            'footer' => $invoice->description ? $this->plainText($invoice->description, 1000) : null,
            'terms_and_conditions' => $terms ?: null,
        ];

        $discount = $this->discount($invoice);

        if ($discount !== null) {
            $payload['discount'] = $discount;
        }

        return array_filter($payload, fn($value): bool => $value !== null && $value !== '' && $value !== []);
    }

    protected function items(Invoice $invoice, string $currency): array
    {
        $items = [];
        $vatRate = $this->vatRate($invoice);

        foreach ((array) $invoice->items as $item) {
            $type = $item['type'] ?? null;
            $data = $item['data'] ?? [];

            if ($type === 'remise') {
                continue;
            }

            $total = $this->lineTotal($data);

            if ($total <= 0) {
                continue;
            }

            $quantity = max((float) ($data['qty'] ?? 1), 0.01);
            $unitPrice = isset($data['cu']) && is_numeric($data['cu'])
                ? (float) $data['cu']
                : $total / $quantity;

            $qontoItem = [
                'title' => Str::limit((string) ($data['title'] ?? $data['product_title'] ?? $invoice->title ?? 'Prestation'), 250, ''),
                'quantity' => $this->decimal($quantity),
                'unit' => $this->unit($data['type'] ?? $type),
                'unit_price' => [
                    'value' => $this->money($unitPrice),
                    'currency' => $currency,
                ],
                'vat_rate' => $this->decimal($vatRate),
            ];

            if (filled($data['description'] ?? null)) {
                $qontoItem['description'] = $this->plainText((string) $data['description'], 1000);
            }

            $vatExemptionReason = config('qonto.client_invoices.vat_exemption_reason');

            if ($vatRate === 0.0 && filled($vatExemptionReason)) {
                $qontoItem['vat_exemption_reason'] = $vatExemptionReason;
            }

            $items[] = $qontoItem;
        }

        if ($items === []) {
            $amount = max((float) $invoice->total_ht, 0.01);
            $items[] = [
                'title' => Str::limit((string) ($invoice->title ?: $invoice->code ?: 'Prestation'), 250, ''),
                'quantity' => '1',
                'unit' => config('qonto.client_invoices.default_unit', 'piece'),
                'unit_price' => [
                    'value' => $this->money($amount),
                    'currency' => $currency,
                ],
                'vat_rate' => $this->decimal($vatRate),
            ];
        }

        return $items;
    }

    protected function discount(Invoice $invoice): ?array
    {
        $gross = (float) ($invoice->total_ht_br ?: 0);
        $net = (float) ($invoice->total_ht ?: 0);
        $discountAmount = max(0, $gross - $net);

        if ($gross <= 0 || $discountAmount <= 0) {
            return null;
        }

        return [
            'type' => 'percentage',
            'value' => $this->decimal(min($discountAmount / $gross, 0.9999)),
        ];
    }

    protected function storeClient(Company $company, ClientData $client): void
    {
        $company->forceFill([
            'qonto_client_id' => $client->id,
            'qonto_e_invoicing_reachable' => $client->eInvoicingReachable,
            'qonto_client_synced_at' => now(),
            'qonto_raw' => data_get($client->raw, 'client', $client->raw),
        ])->save();
    }

    protected function storeInvoice(Invoice $invoice, ClientInvoiceData $qontoInvoice, array $downloaded = []): void
    {
        $invoice->forceFill([
            'qonto_invoice_id' => $qontoInvoice->id,
            'qonto_invoice_number' => $qontoInvoice->number,
            'qonto_invoice_url' => $qontoInvoice->invoiceUrl,
            'qonto_attachment_id' => $qontoInvoice->attachmentId,
            'qonto_status' => $qontoInvoice->status,
            'qonto_einvoicing_status' => $qontoInvoice->einvoicingStatus,
            'qonto_pdf_disk' => $downloaded['disk'] ?? $invoice->qonto_pdf_disk,
            'qonto_pdf_path' => $downloaded['path'] ?? $invoice->qonto_pdf_path,
            'qonto_synced_at' => now(),
            'qonto_finalized_at' => $qontoInvoice->finalizedAt ? Carbon::parse($qontoInvoice->finalizedAt) : $invoice->qonto_finalized_at,
            'qonto_raw' => data_get($qontoInvoice->raw, 'client_invoice', $qontoInvoice->raw),
        ])->save();
    }

    protected function issueDate(Invoice $invoice): Carbon
    {
        return $invoice->submited_at ? Carbon::parse($invoice->submited_at) : now();
    }

    protected function dueDate(Invoice $invoice, Carbon $issueDate): Carbon
    {
        $modalite = Str::of((string) $invoice->modalite)->ascii()->lower()->toString();

        if (str_contains($modalite, 'fin de mois')) {
            return $issueDate->copy()->endOfMonth();
        }

        if (preg_match('/(\d+)/', $modalite, $matches)) {
            return $issueDate->copy()->addDays((int) $matches[1]);
        }

        return $issueDate->copy()->addDays((int) config('qonto.client_invoices.default_due_days', 30));
    }

    protected function paymentIban(): string
    {
        $iban = $this->bankAccounts->requireDefault()->iban;

        if (! filled($iban)) {
            throw ValidationException::withMessages([
                'qonto_bank_account_id' => 'Le compte bancaire Qonto par défaut doit avoir un IBAN pour publier une facture.',
            ]);
        }

        return $iban;
    }

    protected function lineTotal(array $data): float
    {
        if (isset($data['total']) && is_numeric($data['total'])) {
            return abs((float) $data['total']);
        }

        if (isset($data['cu'], $data['qty']) && is_numeric($data['cu']) && is_numeric($data['qty'])) {
            return abs((float) $data['cu'] * (float) $data['qty']);
        }

        return 0.0;
    }

    protected function vatRate(Invoice $invoice): float
    {
        if (! $invoice->has_tva && (float) $invoice->tx_tva === 0.0) {
            return 0.0;
        }

        return max(0.0, (float) $invoice->tx_tva);
    }

    protected function unit(?string $type): string
    {
        return match ($type) {
            'heures' => 'hour',
            'jours' => 'day',
            default => config('qonto.client_invoices.default_unit', 'piece'),
        };
    }

    protected function countryCode(Company $company): string
    {
        $country = $company->country;

        if ($country instanceof \BackedEnum) {
            return strtoupper((string) $country->value);
        }

        return strtoupper((string) ($country ?: config('qonto.clients.default_country_code', 'FR')));
    }

    protected function cleanIdentifier(?string $value): ?string
    {
        $clean = preg_replace('/\D+/', '', (string) $value);

        return $clean ?: null;
    }

    protected function plainText(string $value, int $limit): string
    {
        return Str::limit(Str::of(strip_tags($value))->replace(['#', '*', '`'], '')->squish()->toString(), $limit, '');
    }

    protected function money(float $value): string
    {
        return number_format(round($value, 2), 2, '.', '');
    }

    protected function decimal(float $value): string
    {
        $formatted = rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
