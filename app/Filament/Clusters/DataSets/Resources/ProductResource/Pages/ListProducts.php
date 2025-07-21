<?php

namespace App\Filament\Clusters\DataSets\Resources\ProductResource\Pages;

use Filament\Actions;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\Exports\ProductExporter;
use App\Services\Imports\ProductImporter;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Pages\ListRecords;
use App\Services\Exports\Product\ProductMaatExporter;
use App\Filament\Components\Actions\ExportMaatExcelAction;
use App\Filament\Components\Actions\ImportMaatExcelAction;
use App\Filament\Clusters\DataSets\Resources\ProductResource;
use Filament\Actions\Exports\Enums\ExportFormat;

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
                ExportAction::make()
                    ->exporter(ProductExporter::class)
                    ->label('Exporter')
                    ->formats([
                        ExportFormat::Xlsx,
                    ]),
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
