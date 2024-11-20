<?php

namespace App\Filament\Clusters\Crm\Resources\SupplierInvoiceResource\Pages;

use App\Filament\Clusters\Crm\Resources\SupplierInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSupplierInvoice extends CreateRecord
{
    protected static string $resource = SupplierInvoiceResource::class;

    public function getSubNavigation(): array
    {
        if (filled($cluster = static::getCluster())) {
            return $this->generateNavigationItems($cluster::getClusteredComponents());
        }

        return [];
    }
}
