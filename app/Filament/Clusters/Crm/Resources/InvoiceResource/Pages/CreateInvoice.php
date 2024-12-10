<?php

namespace App\Filament\Clusters\Crm\Resources\InvoiceResource\Pages;

use App\Filament\Clusters\Crm\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;
}
