<?php

namespace App\Services\Exports\Product;

use App\Models\Product;
use App\Contracts\HasExportData;
use Illuminate\Support\Collection;
use App\Services\Exports\BaseExcelTemplate;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use App\Services\Document\Contracts\DocumentProducer;


class ProductMaatExporter extends BaseExcelTemplate implements DocumentProducer
{
    public function getColumns(): array
    {
        return [
            'code' => 'Code',
            'title' => 'Titre',
            'unit_price' => 'Prix Unitaire',
        ];
    }

    public function getFileName(array $options = []): string
    {
        return 'produits.xlsx';
    }

    public function getData(array $options = []): Collection
    {
        return Product::select(array_keys(self::getColumns()))->get();
    }

    public function getColumnFormats(): array
    {
        return [
            'C' => NumberFormat::FORMAT_CURRENCY_EUR,
        ];
    }
}
