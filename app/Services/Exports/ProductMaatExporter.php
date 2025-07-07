<?php

namespace App\Services\Exports;

use App\Contracts\HasExportData;
use App\Models\Product;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;


class ProductMaatExporter implements HasExportData
{
    public static function getColumns(): array
    {
        return [
            'code' => 'Code',
            'title' => 'Titre',
            'unit_price' => 'Prix Unitaire',
        ];
    }

    public static function getFileName(): string
    {
        return 'produits.xlsx';
    }

    public static function getData(array $options = []): Collection
    {
        return Product::select(array_keys(self::getColumns()))->get();
    }

    public static function getColumnFormats(): array
    {
        return [
            'C' => NumberFormat::FORMAT_CURRENCY_EUR,
        ];
    }
}
