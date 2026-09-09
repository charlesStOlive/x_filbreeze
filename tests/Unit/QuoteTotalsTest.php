<?php

namespace Tests\Unit;

use App\Filament\Clusters\Crm\Resources\QuoteResource;
use App\Models\Quote;
use PHPUnit\Framework\TestCase;

class QuoteTotalsTest extends TestCase
{
    public function test_it_recalculates_stale_line_and_quote_totals(): void
    {
        $quote = new Quote([
            'items' => [
                [
                    'type' => 'product',
                    'data' => [
                        'type' => 'jours',
                        'cu' => 200,
                        'qty' => 2,
                        'total' => 100,
                    ],
                ],
                [
                    'type' => 'product',
                    'data' => [
                        'type' => 'heures',
                        'cu' => 100,
                        'qty' => 8,
                        'total' => 50,
                        'is_option' => true,
                    ],
                ],
                [
                    'type' => 'tasks',
                    'data' => [
                        'cu' => 50,
                        'qty' => 2,
                        'total' => 20,
                    ],
                ],
                [
                    'type' => 'forfait',
                    'data' => [
                        'total' => 300,
                        'is_option' => true,
                    ],
                ],
                [
                    'type' => 'remise',
                    'data' => ['total' => 50],
                ],
            ],
        ]);

        $quote->recalculateTotalsFromItems();

        $this->assertEquals(400.0, $quote->items[0]['data']['total']);
        $this->assertEquals(800.0, $quote->items[1]['data']['total']);
        $this->assertEquals(100.0, $quote->items[2]['data']['total']);
        $this->assertEquals(1600.0, $quote->total_ht_br);
        $this->assertEquals(1550.0, $quote->total_ht);
        $this->assertEquals(500.0, $quote->total_avant_options);
        $this->assertEquals(1100.0, $quote->total_options);
        $this->assertEquals(3.0, $quote->total_jours);
    }

    public function test_reactive_totals_do_not_depend_on_stale_line_totals(): void
    {
        $items = [
            [
                'type' => 'product',
                'data' => [
                    'type' => 'jours',
                    'cu' => 200,
                    'qty' => 2,
                    'total' => 100,
                ],
            ],
            [
                'type' => 'product',
                'data' => [
                    'type' => 'heures',
                    'cu' => 100,
                    'qty' => 8,
                    'total' => 50,
                    'is_option' => true,
                ],
            ],
            [
                'type' => 'remise',
                'data' => ['total' => 50],
            ],
        ];
        $totals = [];
        $livewire = new class
        {
            public function dispatch(string $event): void {}
        };

        QuoteResource::updateItemsTotal(
            function (string $path, mixed $value) use (&$totals): void {
                $totals[$path] = $value;
            },
            fn (string $path): mixed => $path === 'items' ? $items : null,
            $livewire,
        );

        $this->assertEquals(1200.0, $totals['total_ht_br']);
        $this->assertEquals(1150.0, $totals['total_ht']);
        $this->assertEquals(400.0, $totals['total_avant_options']);
        $this->assertEquals(800.0, $totals['total_options']);
        $this->assertEquals(3.0, $totals['total_jours']);
    }
}
