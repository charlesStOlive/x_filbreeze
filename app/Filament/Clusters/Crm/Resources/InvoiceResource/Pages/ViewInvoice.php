<?php

namespace App\Filament\Clusters\Crm\Resources\InvoiceResource\Pages;

use App\Filament\Clusters\Crm\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}