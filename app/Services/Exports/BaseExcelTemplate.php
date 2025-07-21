<?php

namespace App\Services\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use App\Services\Exports\FromCollectionExport;
use App\Services\Document\Dto\GeneratedDocumentDTO;
use App\Services\Document\Contracts\DocumentProducer;
use App\Services\Document\Concerns\InteractsWithDocumentProducer;

abstract class BaseExcelTemplate implements DocumentProducer
{

    use InteractsWithDocumentProducer;

    protected array $options = [];

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    abstract public function getColumns(): array;

    abstract public function getFileName(array $options = []): string;

    abstract public function getData(array $options = []): Collection;

    public function getColumnFormats(): array
    {
        return [];
    }

    public static function key(): string
    {
        return 'excel_' . str(class_basename(static::class))->kebab();
    }

    public static function label(): string
    {
        return 'Excel - ' . str(class_basename(static::class))->headline();
    }

    public static function getDefaultOptions(): array
    {
        return [];
    }

    public static function getForm(array $defaultOptions = []): array
    {
        return [];
    }


    public function createDocument(array $options = []): string
    {
        $options = array_merge($this->options, $options);

        $rows = $this->getData($options)
            ->map(fn($item) => collect($this->getColumns())->keys()->map(
                fn($key) => data_get($item, $key)
            ));

        $fileName = uniqid('excel_', true) . '.xlsx';
        $relativePath = 'tmp/' . $fileName;

        \Maatwebsite\Excel\Facades\Excel::store(
            new \App\Services\Exports\FromCollectionExport(
                rows: $rows,
                headings: array_values($this->getColumns()),
                columnFormats: $this->getColumnFormats($options),
            ),
            $relativePath,
            'local'
        );

        return \Storage::disk('local')->path($relativePath);
    }

    public function generateFile(array $options = []): \App\Services\Document\Dto\GeneratedDocumentDTO
    {
        $fileName = $this->getFileName($options) . '.xlsx';
        $relativePath = static::getExportDirectory() . '/' . $fileName;
        $publicPath = \Storage::disk('public')->path($relativePath);

        $tempPath = $this->createDocument($options);

        \Storage::disk('public')->put($relativePath, file_get_contents($tempPath));
        @unlink($tempPath);

        return new \App\Services\Document\Dto\GeneratedDocumentDTO(
            path: $publicPath,
            name: $fileName,
            mime: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
    }

    public static function hasForm(): bool
    {
        return method_exists(static::class, 'getForm') && !empty(static::getForm(static::getDefaultOptions()));
    }
}
