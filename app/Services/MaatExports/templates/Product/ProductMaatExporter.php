<?php

namespace App\Services\MaatExports\Templates\Product;

use App\Models\Product;
use Illuminate\Support\Collection;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Toggle;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use App\Services\MaatExports\Base\BaseExcelTemplate;

class ProductMaatExporter extends BaseExcelTemplate
{
    public static function key(): string
    {
        return 'base_export';
    }

    public static function label(): string
    {
        return 'Export produits';
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
                    ->default($this->getOption('set_euro_column'))
                    ->live(),
            ]),
        ];
    }

    public function getFileName(array $options = []): string
    {
        return 'produits';
    }

    public function getData(array $options = []): Collection
    {
        // Fusion propre des options
        $options = $this->getMergedOptions($options);

        $products = Product::with(['gamme'])->get();

        return $products->map(function (Product $product) {
            return [
                'code' => $product->code,
                'title' => $product->title,
                'unit_price' => $product->unit_price,
                'gamme' => $product->gamme?->slug,
                'type' => $product->type?->value,
            ];
        });
    }

    public function getColumnFormats(array $options = []): array
    {
        $options = $this->getMergedOptions($options);

        return $options['set_euro_column']
            ? ['C' => NumberFormat::FORMAT_CURRENCY_EUR] // colonne "unit_price"
            : [];
    }
}
