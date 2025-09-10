<?php

namespace App\Filament\Clusters\DataSets\Resources\ProductResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Actions\ActionGroup;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Clusters\DataSets\Resources\ProductResource;
use App\Services\MaatImports\Templates\Product\ProductImporter;
use App\Services\MaatExports\Templates\Product\ProductMaatExporter;
use App\Services\MaatExports\Filament\Actions\ExportMaatExcelListAction;
use App\Services\MaatImports\Filament\Actions\ImportMaatExcelListAction;


class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    public ?string $grouping = 'gamme.name';

    public function getGroupedSelectableTableRecordKeys(?string $group): array
    {
        // Si le groupe est null, retourner un tableau vide
        if ($group === null) {
            return [];
        }
        
        // Extraire seulement la clé du groupe, en ignorant la direction de tri
        if (str_contains($group, ':')) {
            $group = explode(':', $group)[0];
        }
        
        return parent::getGroupedSelectableTableRecordKeys($group);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ActionGroup::make([
                ImportMaatExcelListAction::make('importproduct')
                    ->label('Importer les produits')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->templates([ProductImporter::class])
                    ->modalHeading('Import produits via Excel'),
                ExportMaatExcelListAction::make('exportProduits')
                    ->label('Exporter les produits')
                    ->icon('heroicon-o-cloud-arrow-down')
                    ->templates([ProductMaatExporter::class]),
            ])
                ->label('Import/Export')
                ->icon('heroicon-m-ellipsis-vertical')
                ->color('primary')
                ->button()

        ];
    }
}
