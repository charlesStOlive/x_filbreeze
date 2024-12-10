<?php

namespace App\Filament\Clusters\Crm\Resources\InvoiceResource\Pages;

use App\Filament\Clusters\Crm\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
