<?php

namespace App\Filament\Clusters\Crm\Resources\SupplierInvoiceResource\Pages;

use App\Filament\Clusters\Crm\Resources\SupplierInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSupplierInvoices extends ListRecords
{
    protected static string $resource = SupplierInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
