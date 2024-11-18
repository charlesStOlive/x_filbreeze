<?php

namespace App\Filament\Clusters\Crm\Resources\SectorResource\Pages;

use Filament\Actions;
use Filament\Tables\Table;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Clusters\Crm\Resources\SectorResource;
use App\Filament\Clusters\Crm\Resources\SectorResource\Widgets\SectorWidget;

class ListSectors extends ListRecords
{
    protected static string $resource = SectorResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([])->paginated(false);
    }

 
    protected function getHeaderWidgets(): array
    {
        return [
            SectorWidget::class
        ];
    }
}
