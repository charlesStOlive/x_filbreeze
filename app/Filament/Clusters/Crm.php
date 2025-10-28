<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;
use CharlesStOlive\FilamentPermissionManager\Services\PermissionService;

class Crm extends Cluster
{
    protected static string | \BackedEnum | null $navigationIcon = 'fas-bullseye';

    public static function canAccess(): bool
    {
        return PermissionService::can('crm.*');
    }
}
