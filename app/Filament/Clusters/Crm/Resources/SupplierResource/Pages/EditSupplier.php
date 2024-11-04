<?php

namespace App\Filament\Clusters\Crm\Resources\SupplierResource\Pages;

use App\Filament\Clusters\Crm\Resources\SupplierResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSupplier extends EditRecord
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
