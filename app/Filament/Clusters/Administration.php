<?php

namespace App\Filament\Clusters;

use CharlesStOlive\FilamentPermissionManager\Traits\HasClusterAuthorization;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class Administration extends Cluster
{
    // Accessible (menu et URL) dès qu'une de ses Resources l'est.
    use HasClusterAuthorization;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Administration';

    protected static ?int $navigationSort = 5;
}
