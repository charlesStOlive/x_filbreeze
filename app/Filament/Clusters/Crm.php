<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;
use CharlesStOlive\FilamentPermissionManager\Traits\HasClusterAuthorization;

class Crm extends Cluster
{
    // Accessible (menu et adresse) dès qu'une de ses listes l'est ; avant, il fallait le joker de toute la rubrique.
    use HasClusterAuthorization;

    protected static string | \BackedEnum | null $navigationIcon = 'fas-bullseye';
}
