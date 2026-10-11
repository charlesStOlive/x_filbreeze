<?php

namespace Tests\Feature;

use App\Filament\Resources\DeclarationResource\Pages\EditDeclaration;
use App\Models\Declaration;
use App\Models\User;
use App\Services\Declarations\DeclarationCalculator;
use CharlesStOlive\FilamentQonto\Models\QontoSupplierInvoice;
use CharlesStOlive\FilamentQonto\Models\QontoTransaction;
use CharlesStOlive\FilamentQonto\Services\SupplierInvoiceCoherence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Qonto mélange parfois les pièces : la vérification de cohérence le signale à l'import, et la
 * déclaration montre les paiements avec TVA qu'aucune facture ne fait entrer dans le calcul.
 */
class QontoSupplierInvoiceCoherenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_2024_invoice_joined_to_a_2026_payment_is_flagged(): void
    {
        // Le cas Microsoft : paiement de 14,75 € le 16/09/2026, pièce jointe = facture de 14,04 € du 15/09/2024.
        $payment = $this->transaction('tx-ms', '2026-09-16 00:55:48', 1475, vatCents: 246);
        $wrong = $this->invoice('si-ms', 'E0500TLZY6', '2024-09-15', 1404, 234, $payment);
        $right = $this->invoice('si-ok', 'G178162388', '2026-08-15', 1475, 246, $this->transaction('tx-ok', '2026-08-15 09:54:52', 1475));

        $this->assertSame(1, app(SupplierInvoiceCoherence::class)->checkAll());

        $this->assertSame(['amount_mismatch', 'payment_date_gap'], array_column($wrong->refresh()->coherence_issues, 'code'));
        $this->assertSame([], $right->refresh()->coherence_issues);
        $this->assertFalse($right->hasCoherenceIssues());
    }

    public function test_duplicates_shared_transactions_and_odd_vat_rates_are_flagged(): void
    {
        $payment = $this->transaction('tx-1', '2026-10-01 10:00:00', 1200);
        $first = $this->invoice('si-1', 'EUINFR26-1310550', '2026-10-01', 1200, 200, $payment);
        $this->invoice('si-2', 'euinfr26-1310550', '2026-10-01', 1200, 200, $payment);
        $other = $this->invoice('si-3', 'AUTRE-1', '2026-10-01', 1200, 200, $payment);
        // 18,83 de TVA sur 75,31 HT : 25 %, alors que la facture affiche 20 %.
        $odd = $this->invoice('si-4', '556788585', '2026-10-01', 9414, 1883, null, ['taxes' => [['tax_rate' => '20']]]);

        app(SupplierInvoiceCoherence::class)->checkAll();

        $this->assertSame(['duplicate', 'shared_transaction'], array_column($first->refresh()->coherence_issues, 'code'));
        $this->assertSame(['shared_transaction', 'shared_transaction'], array_column($other->refresh()->coherence_issues, 'code'));
        $this->assertSame(['unusual_vat_rate'], array_column($odd->refresh()->coherence_issues, 'code'));
        $this->assertStringContainsString('25,0 %', $odd->coherenceSummary());
        $this->assertStringContainsString('taux affiché de 20 %', $odd->coherenceSummary());
    }

    public function test_the_declaration_lists_the_payments_with_vat_that_no_invoice_counts(): void
    {
        $microsoft = $this->transaction('tx-ms', '2026-09-16 00:55:48', 1475, vatCents: 246);
        $this->invoice('si-ms', 'E0500TLZY6', '2024-09-15', 1404, 234, $microsoft);
        $this->invoice('si-ok', 'FAC-1', '2026-09-01', 2400, 400, $this->transaction('tx-ok', '2026-09-02 10:00:00', 2400, vatCents: 400));
        $this->transaction('tx-receipt', '2026-09-20 10:00:00', 600, vatCents: null, attachments: ['att-1']);
        $this->transaction('tx-fee', '2026-09-22 10:00:00', 35, vatCents: 0, attachments: ['att-2']);
        $this->transaction('tx-october', '2026-10-02 10:00:00', 999, vatCents: 166);

        $september = Declaration::create([
            'type' => Declaration::TYPE_VAT,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'covered_months' => ['2026-09'],
            'status' => 'draft',
        ]);

        $rows = app(DeclarationCalculator::class)->uncoveredVatTransactions($september);

        $this->assertSame(['tx-ms', 'tx-receipt'], $rows->map(fn (array $row): string => $row['transaction']->qonto_id)->all());
        $this->assertSame('Facture E0500TLZY6 datée du 15/09/2024', $rows[0]['cause']);
        $this->assertSame(246, $rows[0]['vat_cents']);
        $this->assertSame('Justificatif hors Factures fournisseur', $rows[1]['cause']);

        // Le volet de la déclaration les montre, avec la facture rattachée signalée.
        app(SupplierInvoiceCoherence::class)->checkAll();
        $september->forceFill(['calculation_details' => [
            'client_invoice_ids' => [],
            'qonto_supplier_invoice_ids' => QontoSupplierInvoice::query()->where('qonto_id', 'si-ok')->pluck('id')->all(),
        ]])->save();
        $this->actingAs(User::factory()->withPermissions('panels.admin.access', 'declaration.*')->create());

        Livewire::test(EditDeclaration::class, ['record' => $september->getKey()])
            ->assertOk()
            ->assertSee('Paiements avec TVA non comptés')
            ->assertSee('Facture E0500TLZY6 datée du 15/09/2024')
            ->assertSee('FAC-1');
    }

    public function test_the_payment_gap_is_measured_outside_the_issue_to_due_window(): void
    {
        $gap = fn (string $issued, ?string $due, string $paid): int => SupplierInvoiceCoherence::paymentGapDays(
            now()->parse($issued),
            $due ? now()->parse($due) : null,
            now()->parse($paid),
        );

        $this->assertSame(0, $gap('2026-09-01', '2026-10-01', '2026-09-20'));
        $this->assertSame(10, $gap('2026-09-01', '2026-10-01', '2026-10-11'));
        $this->assertSame(3, $gap('2026-09-04', null, '2026-09-01'));
        $this->assertSame(731, $gap('2024-09-15', '2024-09-15', '2026-09-16'));
    }

    private function transaction(string $qontoId, string $settledAt, int $amountCents, ?int $vatCents = null, array $attachments = []): QontoTransaction
    {
        return QontoTransaction::create([
            'qonto_id' => $qontoId,
            'side' => 'debit',
            'amount_cents' => $amountCents,
            'currency' => 'EUR',
            'account_amount_cents' => $amountCents,
            'account_currency' => 'EUR',
            'label' => strtoupper($qontoId),
            'settled_at' => $settledAt,
            'attachment_ids' => $attachments,
            'raw' => ['id' => $qontoId, 'vat_amount_cents' => $vatCents],
        ]);
    }

    private function invoice(string $qontoId, string $number, string $invoiceAt, int $amountCents, int $vatCents, ?QontoTransaction $transaction, array $raw = []): QontoSupplierInvoice
    {
        return QontoSupplierInvoice::create([
            'qonto_id' => $qontoId,
            'number' => $number,
            'invoice_at' => $invoiceAt,
            'due_at' => $invoiceAt,
            'amount_cents' => $amountCents,
            'vat_cents' => $vatCents,
            'amount_without_vat_cents' => $amountCents - $vatCents,
            'currency' => 'EUR',
            'account_currency' => 'EUR',
            'account_vat_cents' => $vatCents,
            'qonto_transaction_id' => $transaction?->getKey(),
            'raw' => array_merge(['id' => $qontoId], $raw),
        ]);
    }
}
