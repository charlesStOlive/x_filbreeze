<?php

namespace Tests\Unit;

use App\Models\Invoice;
use PHPUnit\Framework\TestCase;

class InvoiceTotalsTest extends TestCase
{
    public function test_it_recalculates_stale_product_and_invoice_totals(): void
    {
        $invoice = new Invoice([
            'items' => [
                [
                    'type' => 'product',
                    'data' => [
                        'type' => 'jours',
                        'cu' => 250,
                        'qty' => 3,
                        'total' => 100,
                    ],
                ],
                [
                    'type' => 'remise',
                    'data' => ['total' => 50],
                ],
            ],
            'tx_tva' => 0.2,
        ]);

        $invoice->recalculateTotalsFromItems();

        $this->assertEquals(750.0, $invoice->items[0]['data']['total']);
        $this->assertEquals(750.0, $invoice->total_ht_br);
        $this->assertEquals(700.0, $invoice->total_ht);
        $this->assertEquals(140.0, $invoice->tva);
        $this->assertEquals(840.0, $invoice->total_ttc);
    }
}
