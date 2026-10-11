<?php

namespace Tests\Feature;

use CharlesStOlive\FilamentQonto\Http\QontoClient;
use CharlesStOlive\FilamentQonto\Models\QontoSupplierInvoice;
use CharlesStOlive\FilamentQonto\Models\QontoTransaction;
use CharlesStOlive\FilamentQonto\Services\QontoSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeQontoClient;
use Tests\TestCase;

class QontoSyncTest extends TestCase
{
    use RefreshDatabase;

    private FakeQontoClient $qonto;

    protected function setUp(): void
    {
        parent::setUp();

        config(['qonto.accounts.default_bank_account_id' => 'acc-1']);
    }

    public function test_supplier_invoices_are_read_page_after_page_and_only_the_vanished_ones_are_deleted(): void
    {
        $this->fakeQonto(supplierInvoicePages: [
            1 => [$this->supplierInvoice('si-1'), $this->supplierInvoice('si-2')],
            2 => [$this->supplierInvoice('si-3')],
        ]);

        // Sur la page 2 : son rapprochement local doit survivre à la synchronisation.
        QontoSupplierInvoice::create(['qonto_id' => 'si-3', 'matched_at' => '2026-09-01 10:00:00', 'supplier_invoice_id' => 7]);
        QontoSupplierInvoice::create(['qonto_id' => 'si-gone']);

        $result = app(QontoSyncService::class)->supplierInvoices();

        $this->assertSame(3, $result['synced']);
        $this->assertSame(1, $result['deleted']);
        $this->assertEqualsCanonicalizing(['si-1', 'si-2', 'si-3'], QontoSupplierInvoice::query()->pluck('qonto_id')->all());
        $this->assertSame(7, QontoSupplierInvoice::query()->where('qonto_id', 'si-3')->value('supplier_invoice_id'));
        $this->assertSame([1, 2], array_map(
            fn (array $call): int => $call['options']['query']['page'],
            $this->qonto->callsTo('GET', 'supplier_invoices'),
        ));
    }

    public function test_local_corrections_survive_a_sync(): void
    {
        $this->fakeQonto(supplierInvoicePages: [1 => [$this->supplierInvoice('si-1')]]);

        QontoSupplierInvoice::create([
            'qonto_id' => 'si-1',
            'excluded_at' => '2026-10-11 10:00:00',
            'excluded_reason' => 'Doublon',
            'corrected_invoice_at' => '2026-09-20',
            'corrected_vat_cents' => 150,
        ]);

        app(QontoSyncService::class)->supplierInvoices();

        $invoice = QontoSupplierInvoice::query()->sole();
        $this->assertSame('2026-09-15', $invoice->invoice_at->toDateString(), 'La valeur lue par Qonto est rafraîchie…');
        $this->assertSame('2026-09-20', $invoice->corrected_invoice_at->toDateString(), '… la correction reste.');
        $this->assertSame(150, $invoice->corrected_vat_cents);
        $this->assertSame('Doublon', $invoice->excluded_reason);
        $this->assertTrue($invoice->isExcluded());
        $this->assertSame([], $invoice->coherence_issues, 'Une facture exclue n\'est plus vérifiée.');
    }

    public function test_transactions_resume_from_the_last_qonto_update(): void
    {
        $this->fakeQonto();
        $lastQuery = fn (): array => collect($this->qonto->callsTo('GET', 'transactions'))->last()['options']['query'];

        // Première synchronisation : tout l'historique.
        app(QontoSyncService::class)->transactions(['per_page' => 100]);
        $this->assertArrayNotHasKey('updated_at_from', $lastQuery());

        QontoTransaction::create(['qonto_id' => 'tx-1', 'bank_account_id' => 'acc-1', 'qonto_updated_at' => '2026-10-05 12:00:00']);

        // Les suivantes : ce qui a changé depuis, avec un jour de marge.
        app(QontoSyncService::class)->transactions(['per_page' => 100]);
        $this->assertSame('2026-10-04T12:00:00+00:00', $lastQuery()['updated_at_from']);
        $this->assertSame('acc-1', $lastQuery()['bank_account_id']);

        // Tout reprendre, sur demande.
        app(QontoSyncService::class)->transactions(['per_page' => 100], full: true);
        $this->assertArrayNotHasKey('updated_at_from', $lastQuery());
    }

    /** @param array<int, array<int, array<string, mixed>>> $supplierInvoicePages */
    private function fakeQonto(array $supplierInvoicePages = [1 => []]): void
    {
        $this->qonto = new FakeQontoClient(function (string $method, string $uri, array $options) use ($supplierInvoicePages): array {
            $page = (int) ($options['query']['page'] ?? 1);
            $lastPage = max(array_keys($supplierInvoicePages));

            return match ($uri) {
                'organization' => ['organization' => ['bank_accounts' => [['id' => 'acc-1', 'iban' => 'FR7600000000000000000000000', 'slug' => 'main']]]],
                'transactions' => ['transactions' => [], 'meta' => ['next_page' => null]],
                'supplier_invoices' => [
                    'supplier_invoices' => $supplierInvoicePages[$page] ?? [],
                    'meta' => ['next_page' => $page < $lastPage ? $page + 1 : null],
                ],
            };
        });

        $this->app->instance(QontoClient::class, $this->qonto);
    }

    private function supplierInvoice(string $id): array
    {
        return [
            'id' => $id,
            'invoice_number' => strtoupper($id),
            'status' => 'paid',
            'issue_date' => '2026-09-15',
            'total_amount' => ['value' => '12.00', 'currency' => 'EUR'],
            'total_tax_amount' => ['value' => '2.00', 'currency' => 'EUR'],
        ];
    }
}
