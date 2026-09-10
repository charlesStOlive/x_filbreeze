<?php

namespace Tests\Feature;

use App\Filament\Widgets\TreasuryOverviewWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TreasuryOverviewWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_without_throwing(): void
    {
        // Pas de credentials Qonto en test : le widget doit dégrader proprement
        // (stat "Erreur"), pas planter le dashboard.
        Livewire::test(TreasuryOverviewWidget::class)
            ->assertOk();
    }
}
