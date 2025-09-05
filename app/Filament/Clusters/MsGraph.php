<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;
use App\Services\PermissionService;

class MsGraph extends Cluster
{
    protected static string | \BackedEnum | null $navigationIcon = 'fab-microsoft';

    public static function canAccess(): bool
    {
        return PermissionService::can('msgraph.*');
    }
}
