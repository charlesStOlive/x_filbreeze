<?php

namespace App\Services\Declarations;

use App\Models\Declaration;
use App\Models\Invoice;
use Carbon\CarbonInterface;
use CharlesStOlive\FilamentQonto\Models\QontoExpenseNote;
use CharlesStOlive\FilamentQonto\Models\QontoSupplierInvoice;
use CharlesStOlive\FilamentQonto\Models\QontoTransaction;
use CharlesStOlive\FilamentQonto\Services\SupplierInvoiceCoherence;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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

    public function calculate(string $type, CarbonInterface|string $start, ?string $cadence = null): array
    {
        $this->assertType($type);

        $cadence ??= Declaration::defaultPeriodFor($type);
        $start = Carbon::parse($start)->startOfDay();
        $end = $cadence === Declaration::PERIOD_QUARTERLY
            ? $start->copy()->addMonthsNoOverflow(2)->endOfMonth()
            : $start->copy()->endOfMonth();

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
                // Ni exclues ici, ni rejetées chez Qonto ; rangées par leur date corrigée s'il y en a une.
                ->counted()
                ->issuedBetween($start->toDateString(), $end->toDateString())
                // Un même achat peut remonter plusieurs fois côté Qonto (import en double,
                // facture rattachée à deux transactions) avec des qonto_id distincts.
                // On garde une seule occurrence par facture fournisseur identifiée via son numéro,
                // en priorisant celle rapprochée d'une transaction.
                ->orderByRaw('matched_at is null')
                ->orderByDesc('matched_at')
                ->get(['id', 'supplier_id', 'number', 'supplier_invoice_id', 'currency', 'vat_cents', 'account_currency', 'account_vat_cents', 'corrected_vat_cents', 'matched_at'])
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

    /**
     * Les paiements Qonto de la période qui portent de la TVA ou un justificatif, mais qu'aucune
     * facture fournisseur ne fait entrer dans une déclaration : la TVA déductible ne vient que du
     * module Factures fournisseur de Qonto, rangée par date d'émission. Soit la pièce n'y est pas,
     * soit la facture rattachée est datée bien loin du paiement (pièce mélangée, date mal lue).
     *
     * @return Collection<int, array{transaction: QontoTransaction, vat_cents: ?int, cause: string}>
     */
    public function uncoveredVatTransactions(Declaration $declaration): Collection
    {
        if ($declaration->type !== Declaration::TYPE_VAT) {
            return collect();
        }

        $from = $declaration->period_start->copy()->startOfDay();
        $to = $declaration->period_end->copy()->endOfDay();

        $transactions = QontoTransaction::query()
            ->where('side', 'debit')
            ->where(fn ($query) => $query
                ->whereBetween('settled_at', [$from, $to])
                ->orWhere(fn ($query) => $query->whereNull('settled_at')->whereBetween('emitted_at', [$from, $to])))
            ->orderByRaw('COALESCE(settled_at, emitted_at)')
            ->get()
            // TVA lue et nulle (frais bancaires, fournisseur étranger) : rien à déduire, rien à signaler.
            ->filter(fn (QontoTransaction $transaction): bool => match ($this->transactionVatCents($transaction)) {
                null => filled($transaction->attachment_ids),
                0 => false,
                default => true,
            });

        $invoices = QontoSupplierInvoice::query()
            ->whereIn('qonto_transaction_id', $transactions->modelKeys())
            ->get()
            ->groupBy('qonto_transaction_id');

        $tolerance = (int) config('qonto.coherence.payment_date_tolerance_days', 31);

        return $transactions
            ->map(function (QontoTransaction $transaction) use ($invoices, $tolerance): ?array {
                $linked = $invoices->get($transaction->getKey(), collect());
                $paidAt = $transaction->settled_at ?? $transaction->emitted_at;

                // Une facture qui compte, datée près du paiement, ou dont la date a été corrigée à la main.
                $covering = $linked->first(fn (QontoSupplierInvoice $invoice): bool => $invoice->isCounted()
                    && $invoice->effectiveInvoiceAt() !== null
                    && ($invoice->corrected_invoice_at !== null
                        || SupplierInvoiceCoherence::paymentGapDays($invoice->invoice_at, $invoice->due_at, $paidAt) <= $tolerance));

                if ($covering) {
                    return null;
                }

                $linkedInvoice = $linked->first();
                $name = $linkedInvoice ? ($linkedInvoice->number ?: '#'.$linkedInvoice->getKey()) : null;

                return [
                    'transaction' => $transaction,
                    'vat_cents' => $this->transactionVatCents($transaction),
                    'cause' => match (true) {
                        $linkedInvoice === null => filled($transaction->attachment_ids) ? 'Justificatif hors Factures fournisseur' : 'Sans justificatif',
                        ! $linkedInvoice->isCounted() => sprintf('Facture %s %s', $name, $linkedInvoice->isExcluded()
                            ? 'exclue : '.$linkedInvoice->excluded_reason
                            : 'rejetée chez Qonto'),
                        $linkedInvoice->invoice_at !== null => sprintf('Facture %s datée du %s', $name, $linkedInvoice->invoice_at->format('d/m/Y')),
                        default => sprintf('Facture %s sans date', $name),
                    },
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * Sommes dues à l'État au titre des déclarations non payées (TVA + URSSAF),
     * pour affichage en trésorerie (widget "Trésorerie théorique").
     */
    public function outstandingStateDueCents(): int
    {
        $urssafRate = (float) config('declarations.urssaf_rate', 0.22);

        return Declaration::query()
            ->where('status', '!=', 'paid')
            ->get()
            ->sum(function (Declaration $declaration) use ($urssafRate): int {
                return match ($declaration->type) {
                    Declaration::TYPE_URSSAF => $this->toCents($declaration->turnover_excluding_tax * $urssafRate),
                    Declaration::TYPE_VAT => $this->toCents($declaration->vat_due),
                    default => 0,
                };
            });
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
            return 'id:'.$invoice->id;
        }

        return 'supplier:'.($invoice->supplier_id ?? 'null').'|number:'.$number;
    }

    private function supplierVatCents(QontoSupplierInvoice $invoice): int
    {
        // Corrigée à la main, en devise du compte.
        if ($invoice->corrected_vat_cents !== null) {
            return $invoice->corrected_vat_cents;
        }

        if (strtoupper((string) $invoice->account_currency) === 'EUR' && $invoice->account_vat_cents !== null) {
            return (int) $invoice->account_vat_cents;
        }

        if (strtoupper((string) $invoice->currency) === 'EUR') {
            return (int) ($invoice->vat_cents ?? 0);
        }

        return 0;
    }

    /** La TVA que Qonto lit sur la transaction elle-même (null : pas lue). */
    private function transactionVatCents(QontoTransaction $transaction): ?int
    {
        $raw = data_get($transaction->raw, 'transaction', $transaction->raw);
        $cents = data_get($raw, 'vat_amount_cents');

        return $cents === null ? null : (int) $cents;
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
