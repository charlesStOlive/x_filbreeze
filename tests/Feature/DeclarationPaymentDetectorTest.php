<?php

namespace Tests\Feature;

use App\Models\Declaration;
use App\Services\Declarations\DeclarationPaymentDetector;
use CharlesStOlive\FilamentQonto\Models\QontoTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeclarationPaymentDetectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_marks_a_filed_declaration_as_paid_when_a_matching_debit_is_found(): void
    {
        $declaration = Declaration::create([
            'type' => Declaration::TYPE_VAT,
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-28',
            'covered_months' => ['2026-02'],
            'vat_collected_cents' => 100000,
            'vat_deductible_cents' => 20000,
            'status' => 'filed',
            'filed_at' => '2026-03-05',
        ]);

        $transaction = QontoTransaction::create([
            'qonto_id' => 'txn-vat-1',
            'side' => 'debit',
            'label' => 'Prelevement DGFIP TVA',
            'settled_at' => '2026-03-10',
        ]);

        $updated = app(DeclarationPaymentDetector::class)->detect();

        $this->assertCount(1, $updated);

        $declaration->refresh();
        $this->assertSame('paid', $declaration->status);
        $this->assertNotNull($declaration->paid_at);
        $this->assertSame($transaction->getKey(), $declaration->qonto_transaction_id);
    }

    public function test_it_ignores_a_debit_that_occurred_before_filing(): void
    {
        Declaration::create([
            'type' => Declaration::TYPE_VAT,
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-28',
            'covered_months' => ['2026-02'],
            'status' => 'filed',
            'filed_at' => '2026-03-05',
        ]);

        // Débit "TVA" antérieur au dépôt : ne peut pas être le paiement de cette déclaration.
        QontoTransaction::create([
            'qonto_id' => 'txn-vat-early',
            'side' => 'debit',
            'label' => 'Prelevement DGFIP TVA',
            'settled_at' => '2026-02-20',
        ]);

        $updated = app(DeclarationPaymentDetector::class)->detect();

        $this->assertCount(0, $updated);
    }

    public function test_it_does_not_reuse_a_transaction_already_matched_to_another_declaration(): void
    {
        $first = Declaration::create([
            'type' => Declaration::TYPE_URSSAF,
            'period_start' => '2026-01-01',
            'period_end' => '2026-02-28',
            'covered_months' => ['2026-01', '2026-02'],
            'status' => 'filed',
            'filed_at' => '2026-03-01',
        ]);

        $second = Declaration::create([
            'type' => Declaration::TYPE_URSSAF,
            'period_start' => '2026-03-01',
            'period_end' => '2026-04-30',
            'covered_months' => ['2026-03', '2026-04'],
            'status' => 'filed',
            'filed_at' => '2026-05-01',
        ]);

        $onlyTransaction = QontoTransaction::create([
            'qonto_id' => 'txn-urssaf-1',
            'side' => 'debit',
            'label' => 'Prelevement URSSAF',
            'settled_at' => '2026-03-05',
        ]);

        $updated = app(DeclarationPaymentDetector::class)->detect();

        // Seule la première déclaration (filed_at le plus ancien) peut réclamer ce débit :
        // il est antérieur à la date de dépôt de la seconde.
        $this->assertCount(1, $updated);
        $this->assertSame($first->id, $updated->first()->id);

        $first->refresh();
        $second->refresh();
        $this->assertSame('paid', $first->status);
        $this->assertSame($onlyTransaction->getKey(), $first->qonto_transaction_id);
        $this->assertSame('filed', $second->status);
    }

    public function test_it_ignores_a_transaction_that_does_not_match_any_pattern(): void
    {
        Declaration::create([
            'type' => Declaration::TYPE_VAT,
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-28',
            'covered_months' => ['2026-02'],
            'status' => 'filed',
            'filed_at' => '2026-03-05',
        ]);

        QontoTransaction::create([
            'qonto_id' => 'txn-unrelated',
            'side' => 'debit',
            'label' => 'Achat fournitures de bureau',
            'settled_at' => '2026-03-10',
        ]);

        $updated = app(DeclarationPaymentDetector::class)->detect();

        $this->assertCount(0, $updated);
    }
}
