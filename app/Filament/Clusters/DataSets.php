<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;
use CharlesStOlive\FilamentPermissionManager\Traits\HasClusterAuthorization;

class DataSets extends Cluster
{
    // Accessible (menu et adresse) dès qu'une de ses listes l'est ; avant, il fallait le joker de toute la rubrique.
    use HasClusterAuthorization;

    // En un mot, comme crm, qonto ou msgraph : c'est le début des permissions (datasets.product.*) et l'adresse (/admin/datasets).
    protected static ?string $slug = 'datasets';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $clusterBreadcrumb = 'Catalogues';

    public static function getNavigationLabel(): string
    {
        return 'Catalogues';
    }
}
