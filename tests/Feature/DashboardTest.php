<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Models\User;
use CharlesStOlive\FilamentTma\Filament\Widgets\MySupportWidget;
use CharlesStOlive\FilamentTma\Filament\Widgets\TimeReportWidget;
use Filament\Facades\Filament;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Le tableau de bord : à gauche mes tickets et projets puis le suivi des temps, à droite le compte puis le reste. */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_shows_the_account_and_the_support_widgets(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->withPermissions('panels.admin.access', 'tma.ticket.viewany', 'tma.task.viewany', 'tma.task.time-report')->create());

        $html = $this->get(Dashboard::getUrl())->assertOk()
            ->assertSeeLivewire(AccountWidget::class)
            ->assertSeeLivewire(MySupportWidget::class)
            ->assertSeeLivewire(TimeReportWidget::class)
            ->assertDontSeeLivewire(FilamentInfoWidget::class)
            ->getContent();

        $this->assertNotContains(FilamentInfoWidget::class, Filament::getWidgets());
        // Sur grand écran, la colonne du Support passe à gauche, sur les deux tiers.
        $this->assertStringContainsString('lg:fi-grid-col-span', $html);
    }
}
