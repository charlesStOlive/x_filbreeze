<?php

namespace Tests\Feature;

use App\Models\Declaration;
use App\Services\Declarations\DeclarationCalculator;
use CharlesStOlive\FilamentQonto\Models\QontoSupplierInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeclarationCalculatorSupplierInvoiceDedupTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_does_not_count_the_same_supplier_invoice_number_twice(): void
    {
        // Qonto peut faire remonter la même facture fournisseur avec deux qonto_id
        // distincts (ex. rattachement à deux transactions). Les deux lignes partagent
        // le même numéro de facture : elle ne doit être comptée qu'une fois.
        QontoSupplierInvoice::create([
            'qonto_id' => 'qonto-1',
            'supplier_id' => 1,
            'number' => 'FA-2026-042',
            'currency' => 'EUR',
            'vat_cents' => 2000,
            'account_currency' => 'EUR',
            'account_vat_cents' => 2000,
            'invoice_at' => '2026-01-15',
            'matched_at' => null,
        ]);

        QontoSupplierInvoice::create([
            'qonto_id' => 'qonto-2',
            'supplier_id' => 1,
            'number' => 'fa-2026-042',
            'currency' => 'EUR',
            'vat_cents' => 2000,
            'account_currency' => 'EUR',
            'account_vat_cents' => 2000,
            'invoice_at' => '2026-01-15',
            'matched_at' => now(),
        ]);

        // Facture distincte, numéro différent : elle doit être comptée en plus.
        QontoSupplierInvoice::create([
            'qonto_id' => 'qonto-3',
            'supplier_id' => 1,
            'number' => 'FA-2026-043',
            'currency' => 'EUR',
            'vat_cents' => 500,
            'account_currency' => 'EUR',
            'account_vat_cents' => 500,
            'invoice_at' => '2026-01-16',
            'matched_at' => null,
        ]);

        $result = app(DeclarationCalculator::class)->calculate(Declaration::TYPE_VAT, '2026-01-01');

        $this->assertSame(2500, $result['vat_deductible_cents']);
        $this->assertCount(2, $result['calculation_details']['qonto_supplier_invoice_ids']);
    }
}
