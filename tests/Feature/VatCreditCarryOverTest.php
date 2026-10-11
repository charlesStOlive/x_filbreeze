<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Declaration;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le crédit de TVA d'une déclaration se reporte sur la suivante, et continue de rouler tant
 * qu'il n'est pas absorbé par de la TVA collectée.
 */
class VatCreditCarryOverTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_credit_of_a_filed_declaration_lowers_the_next_one(): void
    {
        $this->vat('2026-06-01', 'filed', collected: 0, deductible: 722);
        $july = $this->vat('2026-07-01', 'draft', collected: 0, deductible: 0, mode: Declaration::MODE_AUTOMATIC);
        $this->clientInvoicePaid('2026-07-10', vat: 100.00);

        $this->artisan('declarations:refresh')->assertSuccessful();

        $july->refresh();
        $this->assertSame(10000, $july->vat_collected_cents);
        $this->assertSame(7.22, $july->previous_vat_credit);
        $this->assertSame(10000 - 722, $july->vat_due_cents, 'Le recalcul enregistre la TVA due, crédit déduit.');
        $this->assertSame(92.78, $july->vat_due);
        $this->assertSame(0.0, $july->vat_credit);
    }

    public function test_an_unused_credit_keeps_rolling_to_the_following_declarations(): void
    {
        $this->vat('2026-06-01', 'paid', collected: 0, deductible: 1000);
        $july = $this->vat('2026-07-01', 'filed', collected: 200, deductible: 0);
        $august = $this->vat('2026-08-01', 'draft', collected: 1000, deductible: 100);

        // Juillet absorbe 2 € des 10 € de juin : il en reste 8.
        $this->assertSame(10.0, $july->previous_vat_credit);
        $this->assertSame(0.0, $july->vat_due);
        $this->assertSame(8.0, $july->vat_credit);

        // Août : 10 € collectés, 1 € déductible, 8 € de crédit reporté.
        $this->assertSame(8.0, $august->previous_vat_credit);
        $this->assertSame(1.0, $august->vat_due);
        $this->assertSame(0.0, $august->vat_credit);
    }

    private function vat(string $start, string $status, int $collected, int $deductible, string $mode = Declaration::MODE_MANUAL): Declaration
    {
        return Declaration::create([
            'type' => Declaration::TYPE_VAT,
            'period_start' => $start,
            'period_end' => date('Y-m-t', strtotime($start)),
            'covered_months' => [substr($start, 0, 7)],
            'calculation_mode' => $mode,
            'vat_collected_cents' => $collected,
            'vat_deductible_cents' => $deductible,
            'status' => $status,
        ]);
    }

    private function clientInvoicePaid(string $paidAt, float $vat): void
    {
        $company = Company::create(['title' => 'Acme', 'slug' => 'acme']);
        $contact = Contact::create(['first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.test', 'company_id' => $company->getKey()]);
        $invoice = Invoice::create(['title' => 'Solde', 'company_id' => $company->getKey(), 'contact_id' => $contact->getKey(), 'items' => [], 'state' => 'draft']);

        $invoice->forceFill(['payed_at' => $paidAt, 'total_ht' => $vat * 5, 'tva' => $vat])->saveQuietly();
    }
}
