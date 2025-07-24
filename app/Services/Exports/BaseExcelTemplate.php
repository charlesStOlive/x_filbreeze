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

    public function __construct(protected mixed $record = null, array $options = [])
    {
        $this->options = array_merge(static::getDefaultOptions(), $options);
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    abstract public function getColumns(): array;

    abstract public function getFileName(array $options = []): string;

    abstract public function getData(array $options = []): Collection;

    public function getColumnFormats(array $options = []): array
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

    public function getForm(): array
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

        Excel::store(
            new FromCollectionExport(
                rows: $rows,
                headings: array_values($this->getColumns()),
                columnFormats: $this->getColumnFormats($options),
            ),
            $relativePath,
            'local'
        );

        return Storage::disk('local')->path($relativePath);
    }

    public function generateFile(array $options = []): GeneratedDocumentDTO
    {
        $fileName = $this->getFileName($options) . '.xlsx';
        $relativePath = static::getExportDirectory() . '/' . $fileName;
        $publicPath = Storage::disk('public')->path($relativePath);

        $tempPath = $this->createDocument($options);

        Storage::disk('public')->put($relativePath, file_get_contents($tempPath));
        @unlink($tempPath);

        return new GeneratedDocumentDTO(
            path: $publicPath,
            name: $fileName,
            mime: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
    }

    public static function hasForm(): bool
    {
        return true; // car on appelle désormais getForm() sur l'instance
    }
}
