<?php

namespace App\Filament\Clusters\Crm\Resources\ContactResource\Pages;

use App\Filament\Clusters\Crm\Resources\ContactResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContact extends EditRecord
{
    protected static string $resource = ContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
