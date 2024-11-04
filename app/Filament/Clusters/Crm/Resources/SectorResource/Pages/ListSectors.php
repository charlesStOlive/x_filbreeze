<?php

namespace App\Filament\Clusters\Crm\Resources\SectorResource\Pages;

use App\Filament\Clusters\Crm\Resources\SectorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSectors extends ListRecords
{
    protected static string $resource = SectorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
