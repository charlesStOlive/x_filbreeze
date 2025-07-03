<?php

namespace App\Filament\Clusters\DataSets\Resources\ProductResource\Pages;

use Filament\Actions;
use Filament\Actions\ExportAction;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\Exports\ProductExporter;
use App\Services\Imports\ProductImporter;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Clusters\DataSets\Resources\ProductResource;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\ActionGroup::make([
                Actions\Action::make('import')
                    ->label('Importer')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->form([
                        FileUpload::make('file')
                            ->label('Fichier Excel')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                                'text/csv',
                                '.xlsx',
                                '.xls',
                                '.csv',
                            ])
                            ->required(),

                        ...ProductImporter::getForm(), // <- injecté dynamiquement
                    ])
                    ->action(function (array $data) {
                        $options = $data;
                        unset($options['file']);

                        $import = new ProductImporter($options);
                        Excel::import($import, $data['file']);
                        $import->finalize();
                    }),
                ExportAction::make()
                    ->exporter(ProductExporter::class)
                    ->label('Exporter'),
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
