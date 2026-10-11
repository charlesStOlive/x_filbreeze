<?php

namespace Tests\Feature;

use App\Models\Declaration;
use App\Models\User;
use App\Services\Declarations\DeclarationCalculator;
use CharlesStOlive\FilamentQonto\Filament\Clusters\Qonto\Resources\QontoSupplierInvoiceResource\Pages\EditQontoSupplierInvoice;
use CharlesStOlive\FilamentQonto\Filament\Clusters\Qonto\Resources\QontoSupplierInvoiceResource\Pages\ListQontoSupplierInvoices;
use CharlesStOlive\FilamentQonto\Http\QontoClient;
use CharlesStOlive\FilamentQonto\Models\QontoSupplierInvoice;
use CharlesStOlive\FilamentQonto\Models\QontoTransaction;
use CharlesStOlive\FilamentQonto\Services\SupplierInvoiceCoherence;
use CharlesStOlive\FilamentQonto\Services\SupplierInvoiceCorrections;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Support\FakeQontoClient;
use Tests\TestCase;

/**
 * L'API Qonto ne permet ni de modifier ni de supprimer une facture fournisseur : on l'exclut ou on la
 * corrige ici, on la rejette chez Qonto, ou on remplace la pièce de la transaction.
 */
class QontoSupplierInvoiceCorrectionsTest extends TestCase
{
    use RefreshDatabase;

    private FakeQontoClient $qonto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->qonto = new FakeQontoClient(fn (string $method, string $uri): array => match (true) {
            str_ends_with($uri, '/unmark_as_paid') => ['supplier_invoice' => ['id' => 'x', 'status' => 'to_review']],
            str_ends_with($uri, '/reject') => ['supplier_invoice' => ['id' => 'x', 'status' => 'rejected']],
            default => [],
        });
        $this->app->instance(QontoClient::class, $this->qonto);
    }

    public function test_the_vat_skips_excluded_and_rejected_invoices_and_uses_the_corrections(): void
    {
        $counted = $this->invoice('si-ok', '2026-09-05', vat: 1000);
        $this->invoice('si-excluded', '2026-09-06', vat: 500, attributes: ['excluded_at' => now(), 'excluded_reason' => 'Doublon']);
        $this->invoice('si-rejected', '2026-09-07', vat: 300, attributes: ['status' => 'rejected']);
        // Datée de 2024 par Qonto, remise en septembre 2026 à la main, TVA corrigée.
        $redated = $this->invoice('si-redated', '2024-09-15', vat: 234, attributes: ['corrected_invoice_at' => '2026-09-15', 'corrected_vat_cents' => 246]);
        $revat = $this->invoice('si-revat', '2026-09-20', vat: 2000, attributes: ['corrected_vat_cents' => 1500]);
        // Remise en octobre : elle quitte septembre.
        $this->invoice('si-moved', '2026-09-25', vat: 700, attributes: ['corrected_invoice_at' => '2026-10-02']);

        $september = app(DeclarationCalculator::class)->calculate(Declaration::TYPE_VAT, '2026-09-01');

        $this->assertSame(1000 + 246 + 1500, $september['vat_deductible_cents']);
        $this->assertEqualsCanonicalizing(
            [$counted->id, $redated->id, $revat->id],
            $september['calculation_details']['qonto_supplier_invoice_ids'],
        );
        $this->assertSame(700, app(DeclarationCalculator::class)->calculate(Declaration::TYPE_VAT, '2026-10-01')['vat_deductible_cents']);
    }

    public function test_a_paid_duplicate_is_excluded_and_rejected_in_qonto(): void
    {
        $invoice = $this->invoice('si-dup', '2026-09-10', vat: 0);

        app(SupplierInvoiceCorrections::class)->exclude($invoice, 'duplicate', 'déjà reçue par e-mail', rejectInQonto: true);

        // Payée : Qonto la repasse « à traiter », seul statut d'où il accepte un rejet.
        $this->assertSame(['POST supplier_invoices/si-dup/unmark_as_paid', 'POST supplier_invoices/si-dup/reject'], $this->qonto->summary());
        $this->assertSame(
            ['supplier_invoice' => ['declined_note' => 'Doublon : déjà reçue par e-mail']],
            $this->qonto->callsTo('POST', 'supplier_invoices/si-dup/reject')[0]['options']['json'],
            'Pas de code motif hors e-facture : Qonto le refuserait.',
        );

        $invoice->refresh();
        $this->assertSame('rejected', $invoice->status);
        $this->assertSame('Doublon : déjà reçue par e-mail', $invoice->excluded_reason);
        $this->assertFalse($invoice->isCounted());
    }

    public function test_a_french_einvoice_is_rejected_with_its_afnor_reason_code(): void
    {
        $invoice = $this->invoice('si-einvoice', '2026-10-01', vat: 23, attributes: ['status' => 'to_review', 'raw' => ['id' => 'si-einvoice', 'is_einvoice' => true]]);

        app(SupplierInvoiceCorrections::class)->exclude($invoice, 'not_ours', rejectInQonto: true);

        $this->assertSame(['POST supplier_invoices/si-einvoice/reject'], $this->qonto->summary());
        $this->assertSame('DEST_ERR', $this->qonto->calls[0]['options']['json']['supplier_invoice']['declined_reason_code']);
    }

    public function test_the_wrong_attachment_of_a_transaction_is_replaced(): void
    {
        $transaction = QontoTransaction::create([
            'qonto_id' => 'tx-ms',
            'side' => 'debit',
            'amount_cents' => 1475,
            'currency' => 'EUR',
            'settled_at' => '2026-09-16 00:55:48',
            'attachment_ids' => ['att-2024', 'att-other'],
        ]);
        $wrong = $this->invoice('si-2024', '2024-09-15', vat: 234, attributes: ['attachment_id' => 'att-2024', 'qonto_transaction_id' => $transaction->id]);
        $file = UploadedFile::fake()->create('G184959156.pdf', 20, 'application/pdf');

        app(SupplierInvoiceCorrections::class)->replaceAttachment($wrong, $file->getRealPath(), 'G184959156.pdf');

        // Jointe d'abord, l'ancienne retirée ensuite.
        $this->assertSame(['POST transactions/tx-ms/attachments', 'DELETE transactions/tx-ms/attachments/att-2024'], $this->qonto->summary());
        $upload = $this->qonto->calls[0]['options'];
        $this->assertSame('file', $upload['multipart'][0]['name']);
        $this->assertNotEmpty($upload['headers']['X-Qonto-Idempotency-Key']);

        $this->assertSame(['att-other'], $transaction->refresh()->attachment_ids);
        $this->assertTrue($wrong->refresh()->isExcluded());
        $this->assertStringContainsString('Pièce remplacée : sur la transaction du 16/09/2026 par G184959156.pdf', $wrong->excluded_reason);
    }

    public function test_lookalikes_are_flagged_until_one_of_them_is_excluded(): void
    {
        // Le cas DigitalOcean : la même facture importée deux fois, sous deux numéros.
        $first = $this->invoice('si-do-1', '2026-10-01', vat: 0, attributes: ['supplier_name' => 'DIGITALOCEAN LLC', 'number' => '160564205', 'amount_cents' => 9414, 'currency' => 'USD']);
        $second = $this->invoice('si-do-2', '2026-10-01', vat: 1883, attributes: ['supplier_name' => 'DigitalOcean, LLC', 'number' => '556788585', 'amount_cents' => 9414, 'currency' => 'USD', 'amount_without_vat_cents' => 7531]);

        app(SupplierInvoiceCoherence::class)->checkAll();
        $this->assertContains('possible_duplicate', array_column($first->refresh()->coherence_issues, 'code'));
        $this->assertContains('possible_duplicate', array_column($second->refresh()->coherence_issues, 'code'));

        app(SupplierInvoiceCorrections::class)->exclude($second, 'duplicate');

        $this->assertSame([], $first->refresh()->coherence_issues);
        $this->assertSame([], $second->refresh()->coherence_issues);
    }

    public function test_a_corrected_date_is_no_longer_reported_as_far_from_the_payment(): void
    {
        $payment = QontoTransaction::create(['qonto_id' => 'tx-1', 'side' => 'debit', 'amount_cents' => 1404, 'currency' => 'EUR', 'settled_at' => '2026-09-16 00:00:00']);
        $invoice = $this->invoice('si-1', '2024-09-15', vat: 234, attributes: ['qonto_transaction_id' => $payment->id]);

        app(SupplierInvoiceCoherence::class)->checkAll();
        $this->assertSame(['payment_date_gap'], array_column($invoice->refresh()->coherence_issues, 'code'));

        $invoice->forceFill(['corrected_invoice_at' => '2026-09-15'])->save();
        app(SupplierInvoiceCoherence::class)->checkAll();
        $this->assertSame([], $invoice->refresh()->coherence_issues);
    }

    public function test_the_screens_exclude_and_correct_an_invoice(): void
    {
        $this->actingAs(User::factory()->withPermissions('panels.admin.access', 'qonto.*')->create());
        $duplicate = $this->invoice('si-dup', '2026-09-10', vat: 300);
        $misread = $this->invoice('si-misread', '2026-09-12', vat: 1883);

        Livewire::test(ListQontoSupplierInvoices::class)
            ->callAction(TestAction::make('exclude')->table($duplicate), ['reason' => 'duplicate', 'note' => 'copie e-mail'])
            ->assertHasNoFormErrors()
            ->assertNotified('Facture exclue');

        $this->assertSame('Doublon : copie e-mail', $duplicate->refresh()->excluded_reason);
        $this->assertSame([], $this->qonto->calls, 'Sans la case cochée, rien ne part chez Qonto.');

        Livewire::test(EditQontoSupplierInvoice::class, ['record' => $misread->getKey()])
            ->fillForm(['corrected_invoice_at' => '2026-09-30', 'corrected_vat_cents' => '15.69'])
            ->call('save')
            ->assertHasNoFormErrors();

        $misread->refresh();
        $this->assertSame('2026-09-30', $misread->corrected_invoice_at->toDateString());
        $this->assertSame(1569, $misread->corrected_vat_cents);
    }

    private function invoice(string $qontoId, string $invoiceAt, int $vat, array $attributes = []): QontoSupplierInvoice
    {
        return QontoSupplierInvoice::create(array_merge([
            'qonto_id' => $qontoId,
            'number' => strtoupper($qontoId),
            'supplier_name' => strtoupper(str_replace('-', '', $qontoId)).' SAS',
            'status' => 'paid',
            'invoice_at' => $invoiceAt,
            'due_at' => $invoiceAt,
            'amount_cents' => $vat * 6,
            'vat_cents' => $vat,
            'amount_without_vat_cents' => $vat * 5,
            'currency' => 'EUR',
            'account_currency' => 'EUR',
            'account_vat_cents' => $vat,
            'raw' => ['id' => $qontoId],
        ], $attributes));
    }
}
