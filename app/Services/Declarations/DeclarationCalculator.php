<?php

namespace App\Services\Declarations;

use App\Models\Declaration;
use App\Models\Invoice;
use Carbon\CarbonInterface;
use CharlesStOlive\FilamentQonto\Models\QontoExpenseNote;
use CharlesStOlive\FilamentQonto\Models\QontoSupplierInvoice;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class DeclarationCalculator
{
    public function next(string $type): array
    {
        $this->assertType($type);

        $lastDeclaration = Declaration::query()
            ->where('type', $type)
            ->orderByDesc('period_end')
            ->first();

        $start = $lastDeclaration
            ? $lastDeclaration->period_end->copy()->addDay()
            : Carbon::parse(config("declarations.start_from.{$type}"));

        return $this->calculate($type, $start);
    }

    public function calculate(string $type, CarbonInterface|string $start): array
    {
        $this->assertType($type);

        $start = Carbon::parse($start)->startOfDay();
        $end = $type === Declaration::TYPE_VAT
            ? $start->copy()->endOfMonth()
            : $start->copy()->addMonthsNoOverflow(2)->endOfMonth();

        $clientInvoices = Invoice::query()
            ->whereDate('payed_at', '>=', $start->toDateString())
            ->whereDate('payed_at', '<=', $end->toDateString())
            ->get(['id', 'total_ht', 'tva']);

        $turnoverCents = $clientInvoices->sum(
            fn (Invoice $invoice): int => $this->toCents($invoice->total_ht),
        );

        $vatCollectedCents = $clientInvoices->sum(
            fn (Invoice $invoice): int => $this->toCents($invoice->tva),
        );

        $supplierInvoices = collect();
        $expenseNotes = collect();

        if ($type === Declaration::TYPE_VAT) {
            $supplierInvoices = QontoSupplierInvoice::query()
                ->whereDate('invoice_at', '>=', $start->toDateString())
                ->whereDate('invoice_at', '<=', $end->toDateString())
                // Un même achat peut remonter plusieurs fois côté Qonto (import en double,
                // facture rattachée à deux transactions) avec des qonto_id distincts.
                // On garde une seule occurrence par facture fournisseur identifiée via son numéro,
                // en priorisant celle rapprochée d'une transaction.
                ->orderByRaw('matched_at is null')
                ->orderByDesc('matched_at')
                ->get(['id', 'supplier_id', 'number', 'supplier_invoice_id', 'currency', 'vat_cents', 'account_currency', 'account_vat_cents', 'matched_at'])
                ->unique(fn (QontoSupplierInvoice $invoice): string => $this->supplierInvoiceDedupKey($invoice))
                ->values();

            $localSupplierInvoiceIds = $supplierInvoices
                ->pluck('supplier_invoice_id')
                ->filter()
                ->unique()
                ->values();

            $expenseNotes = QontoExpenseNote::query()
                ->whereBetween('spent_at', [$start->toDateString(), $end->toDateString()])
                // Les paiements Qonto sont déjà couverts par les factures fournisseur.
                // Les notes complètent uniquement les dépenses payées hors de Qonto.
                ->where('payment_source', '!=', 'qonto_card')
                ->when(
                    $localSupplierInvoiceIds->isNotEmpty(),
                    fn ($query) => $query->where(function ($query) use ($localSupplierInvoiceIds): void {
                        $query->whereNull('supplier_invoice_id')
                            ->orWhereNotIn('supplier_invoice_id', $localSupplierInvoiceIds);
                    }),
                )
                ->get(['id', 'supplier_invoice_id', 'vat_cents', 'payment_source']);
        }

        $supplierVatCents = $supplierInvoices->sum(
            fn (QontoSupplierInvoice $invoice): int => $this->supplierVatCents($invoice),
        );
        $expenseVatCents = $expenseNotes->sum(fn (QontoExpenseNote $note): int => (int) ($note->vat_cents ?? 0));
        $vatDeductibleCents = $supplierVatCents + $expenseVatCents;
        $previousVatCreditCents = $type === Declaration::TYPE_VAT
            ? $this->previousVatCreditCents($start)
            : 0;
        $vatBalance = $this->vatBalanceCents($vatCollectedCents, $vatDeductibleCents, $previousVatCreditCents);

        return [
            'type' => $type,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'covered_months' => $this->coveredMonths($start, $end),
            'turnover_excluding_tax_cents' => $turnoverCents,
            'vat_collected_cents' => $type === Declaration::TYPE_VAT ? $vatCollectedCents : 0,
            'vat_deductible_cents' => $type === Declaration::TYPE_VAT ? $vatDeductibleCents : 0,
            'vat_due_cents' => $type === Declaration::TYPE_VAT ? $vatBalance['due'] : 0,
            'calculation_details' => [
                'client_invoice_ids' => $clientInvoices->pluck('id')->all(),
                'qonto_supplier_invoice_ids' => $supplierInvoices->pluck('id')->all(),
                'expense_note_ids' => $expenseNotes->pluck('id')->all(),
                'supplier_vat_cents' => $supplierVatCents,
                'expense_note_vat_cents' => $expenseVatCents,
                'client_invoice_count' => $clientInvoices->count(),
                'calculated_at' => now()->toIso8601String(),
            ],
        ];
    }

    public function coveredMonths(CarbonInterface|string $start, CarbonInterface|string $end): array
    {
        $month = Carbon::parse($start)->startOfMonth();
        $lastMonth = Carbon::parse($end)->startOfMonth();
        $months = [];

        while ($month->lte($lastMonth)) {
            $months[] = $month->format('Y-m');
            $month->addMonth();
        }

        return $months;
    }

    public function previousVatCreditCents(CarbonInterface|string $periodStart, ?int $excludeDeclarationId = null): int
    {
        $declarations = Declaration::query()
            ->where('type', Declaration::TYPE_VAT)
            ->whereDate('period_end', '<', Carbon::parse($periodStart)->toDateString())
            ->when(
                $excludeDeclarationId !== null,
                fn ($query) => $query->whereKeyNot($excludeDeclarationId),
            )
            ->orderBy('period_end')
            ->get(['vat_collected_cents', 'vat_deductible_cents']);

        return $declarations->reduce(
            fn (int $credit, Declaration $declaration): int => $this->vatBalanceCents(
                (int) $declaration->vat_collected_cents,
                (int) $declaration->vat_deductible_cents,
                $credit,
            )['credit'],
            0,
        );
    }

    /** @return array{due: int, credit: int} */
    public function vatBalanceCents(int $collected, int $deductible, int $previousCredit = 0): array
    {
        $balance = $collected - $deductible - $previousCredit;

        return [
            'due' => max(0, $balance),
            'credit' => max(0, -$balance),
        ];
    }

    /**
     * Clé de déduplication d'une facture fournisseur Qonto.
     *
     * Sans numéro de facture identifié, on ne peut pas affirmer qu'il s'agit d'un doublon :
     * la ligne reste seule dans son propre groupe (clé basée sur son id).
     */
    private function supplierInvoiceDedupKey(QontoSupplierInvoice $invoice): string
    {
        $number = mb_strtoupper(trim((string) $invoice->number));

        if ($number === '') {
            return 'id:' . $invoice->id;
        }

        return 'supplier:' . ($invoice->supplier_id ?? 'null') . '|number:' . $number;
    }

    private function supplierVatCents(QontoSupplierInvoice $invoice): int
    {
        if (strtoupper((string) $invoice->account_currency) === 'EUR' && $invoice->account_vat_cents !== null) {
            return (int) $invoice->account_vat_cents;
        }

        if (strtoupper((string) $invoice->currency) === 'EUR') {
            return (int) ($invoice->vat_cents ?? 0);
        }

        return 0;
    }

    private function toCents(mixed $amount): int
    {
        return (int) round(((float) ($amount ?? 0)) * 100);
    }

    private function assertType(string $type): void
    {
        if (! array_key_exists($type, Declaration::typeOptions())) {
            throw ValidationException::withMessages([
                'type' => 'Le type de déclaration doit être TVA ou URSSAF.',
            ]);
        }
    }
}
