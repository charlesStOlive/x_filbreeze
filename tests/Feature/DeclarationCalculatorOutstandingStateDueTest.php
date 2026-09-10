<?php

namespace Tests\Feature;

use App\Models\Declaration;
use App\Services\Declarations\DeclarationCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeclarationCalculatorOutstandingStateDueTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sums_unpaid_urssaf_and_vat_declarations_only(): void
    {
        // URSSAF non payée : 22% du CA HT déclaré doit être compté.
        Declaration::create([
            'type' => Declaration::TYPE_URSSAF,
            'period_start' => '2026-01-01',
            'period_end' => '2026-02-28',
            'covered_months' => ['2026-01', '2026-02'],
            'turnover_excluding_tax_cents' => 500000, // 5000,00 €
            'status' => 'draft',
        ]);

        // TVA non payée : la TVA à décaisser doit être comptée.
        Declaration::create([
            'type' => Declaration::TYPE_VAT,
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-28',
            'covered_months' => ['2026-02'],
            'vat_collected_cents' => 200000,
            'vat_deductible_cents' => 50000,
            'status' => 'filed',
        ]);

        // Déclarations payées : ne doivent pas être comptées.
        Declaration::create([
            'type' => Declaration::TYPE_URSSAF,
            'period_start' => '2026-03-01',
            'period_end' => '2026-04-30',
            'covered_months' => ['2026-03', '2026-04'],
            'turnover_excluding_tax_cents' => 999999,
            'status' => 'paid',
        ]);

        Declaration::create([
            'type' => Declaration::TYPE_VAT,
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
            'covered_months' => ['2026-03'],
            'vat_collected_cents' => 999999,
            'vat_deductible_cents' => 0,
            'status' => 'paid',
        ]);

        $dueCents = app(DeclarationCalculator::class)->outstandingStateDueCents();

        // 22% de 5000 € = 1100 € + TVA due de 1500 € (200000 - 50000 cents) = 2600 €.
        $this->assertSame(260000, $dueCents);
    }
}
