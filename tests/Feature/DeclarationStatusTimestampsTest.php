<?php

namespace Tests\Feature;

use App\Models\Declaration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeclarationStatusTimestampsTest extends TestCase
{
    use RefreshDatabase;

    public function test_filed_at_is_stamped_when_status_moves_to_filed(): void
    {
        $declaration = Declaration::create([
            'type' => Declaration::TYPE_VAT,
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-28',
            'covered_months' => ['2026-02'],
            'status' => 'draft',
        ]);

        $this->assertNull($declaration->filed_at);

        $declaration->update(['status' => 'filed']);

        $this->assertNotNull($declaration->fresh()->filed_at);
    }

    public function test_paid_at_is_stamped_when_status_moves_to_paid(): void
    {
        $declaration = Declaration::create([
            'type' => Declaration::TYPE_VAT,
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-28',
            'covered_months' => ['2026-02'],
            'status' => 'filed',
        ]);

        $declaration->update(['status' => 'paid']);

        $this->assertNotNull($declaration->fresh()->paid_at);
    }

    public function test_it_does_not_overwrite_an_existing_filed_at(): void
    {
        $declaration = Declaration::create([
            'type' => Declaration::TYPE_VAT,
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-28',
            'covered_months' => ['2026-02'],
            'status' => 'filed',
            'filed_at' => '2026-03-01 10:00:00',
        ]);

        $declaration->update(['notes' => 'touch']);

        $this->assertSame('2026-03-01 10:00:00', $declaration->fresh()->filed_at->format('Y-m-d H:i:s'));
    }
}
