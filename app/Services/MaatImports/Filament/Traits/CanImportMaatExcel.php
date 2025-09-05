<?php

namespace App\Services\MaatImports\Filament\Traits;

use Filament\Forms\Components\FileUpload;
use Filament\Forms;
use Maatwebsite\Excel\Facades\Excel;

trait CanImportMaatExcel
{
    protected string $maatImporterClass;
    protected mixed $maatImporterRecord = null;

    public function importer(string $importerClass): static
    {
        $this->maatImporterClass = $importerClass;

        $this->form(function (): array {
            $importer = new $this->maatImporterClass(); // instance SANS options pour afficher getForm()
            return array_merge([
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
                    ->required()
                    ->storeFiles(false),
            ], method_exists($importer, 'getForm') ? $importer->getForm() : []);
        });

        $this->action(function (array $data): void {
            $options = $data;
            unset($options['file']);

            $importer = app()->make($this->maatImporterClass, [
                'record' => $this->maatImporterRecord,
                'options' => $options,
            ]);
            Excel::import($importer, $data['file']);
            $importer->finalize();
        });

        return $this;
    }

    public function withRecord(mixed $record): static
    {
        $this->maatImporterRecord = $record;
        return $this;
    }
}
