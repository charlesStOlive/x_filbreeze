<?php

namespace Tests\Feature;

use App\Filament\Resources\DeclarationResource\Pages\EditDeclaration;
use App\Models\Declaration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EditDeclarationInfolistTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_infolist_renders_for_vat_and_urssaf_declarations(): void
    {
        $this->actingAs(User::factory()->create());

        $vat = Declaration::create([
            'type' => Declaration::TYPE_VAT,
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-28',
            'covered_months' => ['2026-02'],
            'vat_collected_cents' => 100000,
            'vat_deductible_cents' => 20000,
            'status' => 'draft',
            'calculation_details' => ['client_invoice_ids' => [], 'qonto_supplier_invoice_ids' => []],
        ]);

        $urssaf = Declaration::create([
            'type' => Declaration::TYPE_URSSAF,
            'period_start' => '2026-01-01',
            'period_end' => '2026-02-28',
            'covered_months' => ['2026-01', '2026-02'],
            'turnover_excluding_tax_cents' => 300000,
            'status' => 'draft',
            'calculation_details' => ['client_invoice_ids' => []],
        ]);

        Livewire::test(EditDeclaration::class, ['record' => $vat->getKey()])->assertOk();
        Livewire::test(EditDeclaration::class, ['record' => $urssaf->getKey()])->assertOk();
    }
}
