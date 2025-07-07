<?php

namespace App\Filament\Components\Concerns;

use Filament\Forms;
use Maatwebsite\Excel\Facades\Excel;

trait CanImportMaatExcel
{
    protected string $maatImporterClass;

    public function importer(string $importerClass): static
    {
        $this->maatImporterClass = $importerClass;

        $this->form(function (): array {
            return array_merge([
                Forms\Components\FileUpload::make('file')
                    ->label('Fichier Excel')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                        'text/csv',
                        '.xlsx',
                        '.xls',
                        '.csv',
                    ])
                    ->required()
                    ->storeFiles(false),
            ], $this->maatImporterClass::getForm());
        });

        if (is_subclass_of($importerClass, \App\Contracts\HasFillForm::class)) {
            $this->fillForm(fn($livewire) => $importerClass::getFillForm($livewire));
        }

        $this->action(function (array $data): void {
            $options = $data;
            unset($options['file']);

            $importer = new ($this->maatImporterClass)($options);
            Excel::import($importer, $data['file']);
            $importer->finalize();
        });

        return $this;
    }
}
