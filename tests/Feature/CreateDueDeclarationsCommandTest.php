<?php

namespace Tests\Feature;

use App\Models\Declaration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CreateDueDeclarationsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_creates_every_due_period_and_stays_idempotent(): void
    {
        Carbon::setTestNow('2026-03-15');
        config([
            'declarations.start_from.vat' => '2026-01-01',
            'declarations.start_from.urssaf' => '2026-01-01',
        ]);

        $this->artisan('declarations:create-due')->assertSuccessful();

        // TVA mensuelle : janvier, février, mars (mars démarre le 01/03 <= 15/03).
        $this->assertSame(3, Declaration::query()->where('type', Declaration::TYPE_VAT)->count());

        // URSSAF (période de 3 mois ici) : une seule période janvier→mars a démarré.
        $this->assertSame(1, Declaration::query()->where('type', Declaration::TYPE_URSSAF)->count());

        $urssaf = Declaration::query()->where('type', Declaration::TYPE_URSSAF)->sole();
        $this->assertSame('2026-01-01', $urssaf->period_start->toDateString());
        $this->assertSame('2026-03-31', $urssaf->period_end->toDateString());
        $this->assertSame(Declaration::MODE_AUTOMATIC, $urssaf->calculation_mode);

        // Rejouer la commande ne doit rien dupliquer.
        $this->artisan('declarations:create-due')->assertSuccessful();

        $this->assertSame(3, Declaration::query()->where('type', Declaration::TYPE_VAT)->count());
        $this->assertSame(1, Declaration::query()->where('type', Declaration::TYPE_URSSAF)->count());
    }
}
