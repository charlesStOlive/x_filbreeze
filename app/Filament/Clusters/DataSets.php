<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class DataSets extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $clusterBreadcrumb = 'Catalogues';

    public static function getNavigationLabel(): string
    {
        return 'Catalogues';
    }
}
