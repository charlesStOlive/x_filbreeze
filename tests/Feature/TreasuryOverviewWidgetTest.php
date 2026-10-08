<?php

namespace Tests\Feature;

use App\Filament\Widgets\TreasuryOverviewWidget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TreasuryOverviewWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_without_throwing(): void
    {
        $this->actingAs(User::factory()->withPermissions('widgets.treasuryoverview.viewany')->create());

        // Pas de credentials Qonto en test : le widget doit dégrader proprement
        // (stat "Erreur"), pas planter le dashboard.
        Livewire::test(TreasuryOverviewWidget::class)
            ->assertOk();
    }

    public function test_it_is_forbidden_without_its_permission(): void
    {
        // Depuis Filament 5.10, un widget vérifie canView() au montage.
        $this->actingAs(User::factory()->create());

        Livewire::test(TreasuryOverviewWidget::class)
            ->assertForbidden();
    }
}
