<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;
use App\Services\PermissionService;

class Crm extends Cluster
{
    protected static ?string $navigationIcon = 'fas-bullseye';

    public static function canAccess(): bool
    {
        return PermissionService::can('crm.*');
    }
}
