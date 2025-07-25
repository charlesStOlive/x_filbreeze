<?php

namespace App\Filament\Clusters\DataSets\Resources\ProductResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Clusters\DataSets\Resources\ProductResource;
use App\Services\MaatImports\Templates\Product\ProductImporter;
use App\Services\MaatExports\Templates\Product\ProductMaatExporter;
use App\Services\MaatExports\Filament\Actions\ExportMaatExcelAction;
use App\Services\MaatImports\Filament\Actions\ImportMaatExcelAction;


class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\ActionGroup::make([
                ImportMaatExcelAction::make('importproduct')
                    ->label('Importer les produits')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->importer(ProductImporter::class)
                    ->modalHeading('Import produits via Excel')
                    ->modalSubmitActionLabel('Importer')
                    ->modalWidth('md'),
                ExportMaatExcelAction::make('exportProduits')
                    ->label('Exporter les produits')
                    ->exporter(ProductMaatExporter::class),
                // Array of actions
            ])
                ->label('Import/Export')
                ->icon('heroicon-m-ellipsis-vertical')
                // ->size(ActionSize::Small)
                ->color('primary')
                ->button()

        ];
    }
}
