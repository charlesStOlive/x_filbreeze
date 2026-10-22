<?php

namespace App\Filament\Pages;

use CharlesStOlive\FilamentTma\Filament\Widgets\AllowanceOverview;
use CharlesStOlive\FilamentTma\Filament\Widgets\MySupportWidget;
use CharlesStOlive\FilamentTma\Filament\Widgets\TimeReportWidget;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Widgets\WidgetConfiguration;

/**
 * Le tableau de bord, en deux colonnes qui se remplissent chacune de haut en bas : à gauche, sur les deux tiers, le
 * Support (le forfait, mes tickets et mes projets, puis le suivi des temps) ; à droite le compte de l'utilisateur, puis
 * les autres widgets, dans leur ordre (`$sort`). Sur un petit écran, une seule colonne, qui commence par le compte.
 */
class Dashboard extends BaseDashboard
{
    /** Les widgets de la colonne de gauche, dans l'ordre. */
    protected const LEFT = [AllowanceOverview::class, MySupportWidget::class, TimeReportWidget::class];

    public function getWidgetsContentComponent(): Component
    {
        $widgets = collect($this->getWidgets());
        $position = fn (string|WidgetConfiguration $widget): int|false => array_search($this->normalizeWidgetClass($widget), self::LEFT, true);
        [$left, $right] = $widgets->partition(fn (string|WidgetConfiguration $widget): bool => $position($widget) !== false);

        return Grid::make(['default' => 1, 'lg' => 3])->schema([
            Group::make(fn (): array => $this->getWidgetsSchemaComponents($right->all()))
                ->columnOrder(['default' => 1, 'lg' => 2]),
            Group::make(fn (): array => $this->getWidgetsSchemaComponents($left->sortBy($position)->all()))
                ->columnSpan(['default' => 1, 'lg' => 2])
                ->columnOrder(['default' => 2, 'lg' => 1]),
        ]);
    }
}
