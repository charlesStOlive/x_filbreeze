<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;
use App\Services\PermissionService;

class DataSets extends Cluster
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $clusterBreadcrumb = 'Catalogues';

    public static function canAccess(): bool
    {
        return PermissionService::can('datasets.*');
    }

    public static function getNavigationLabel(): string
    {
        return 'Catalogues';
    }
}
