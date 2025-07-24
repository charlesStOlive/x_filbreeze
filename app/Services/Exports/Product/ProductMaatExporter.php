<?php

namespace App\Services\Exports\Product;

use App\Models\Product;
use Illuminate\Support\Collection;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Toggle;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use App\Services\Exports\BaseExcelTemplate;
use App\Services\Document\Contracts\DocumentProducer;

class ProductMaatExporter extends BaseExcelTemplate implements DocumentProducer
{
    protected array $options = [];

    public function __construct(protected mixed $record = null, array $options = [])
    {
        $this->options = array_merge(static::getDefaultOptions(), $options);
    }

    public static function getDefaultOptions(): array
    {
        return [
            'set_euro_column' => true,
        ];
    }

    public function getColumns(): array
    {
        return [
            'code' => 'code',
            'title' => 'title',
            'unit_price' => 'unit_price',
            'gamme' => 'gamme',
            'type' => 'type',
        ];
    }

    public function getForm(): array
    {
        return [
            Group::make([
                Toggle::make('set_euro_column')
                    ->label('Activer format €')
                    ->default($this->options['set_euro_column'] ?? true),
            ]),
        ];
    }

    public function getFileName(array $options = []): string
    {
        return 'produits.xlsx';
    }

    public function getData(array $options = []): Collection
    {
        $options = array_merge(static::getDefaultOptions(), $options);

        // Charger les relations nécessaires
        $products = Product::with(['gamme'])->get();

        // Mapper chaque produit en ligne exploitable selon getColumns()
        $productsMap =  $products->map(function (Product $product) {
            return [
                'id' => $product->id,
                'code' => $product->code,
                'title' => $product->title,
                'unit_price' => $product->unit_price,
                'gamme' => $product->gamme?->slug,
                'type' => $product->type?->value,
            ];
        });
        \Log::info('ProductMaatExporter: ' ,  $productsMap->toArray());
        return $productsMap;
    }

    public function getColumnFormats(array $options = []): array
    {
        $options = array_merge(static::getDefaultOptions(), $options);

        return $options['set_euro_column']
            ? ['E' => NumberFormat::FORMAT_CURRENCY_EUR] // colonne "unit_price"
            : [];
    }
}
