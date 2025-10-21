<?php

namespace App\Services\MaatExports\Templates\Product;

use Filament\Schemas\Components\Group;
use App\Models\Product;
use Illuminate\Support\Collection;
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
            'include_id' => false,
        ];
    }

    public function getColumns(array $options = []): array
    {
        $options = $this->getMergedOptions($options);
        
        $columns = [];
        
        if ($options['include_id']) {
            $columns['id'] = 'ID';
        }
        
        $columns = array_merge($columns, [
            'code' => 'code',
            'title' => 'title',
            'unit_price' => 'unit_price',
            'gamme' => 'gamme',
            'type' => 'type',
        ]);
        
        return $columns;
    }

    public function getForm(): array
    {
        return [
            Group::make([
                Toggle::make('include_id')
                    ->label('Ajouter les ID')
                    ->helperText('Obligatoire si vous voulez faire un UPDATE')
                    ->default($this->getOption('include_id'))
                    ->live(),
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

        return $products->map(function (Product $product) use ($options) {
            $data = [];
            
            if ($options['include_id']) {
                $data['id'] = $product->id;
            }
            
            $data = array_merge($data, [
                'code' => $product->code,
                'title' => $product->title,
                'unit_price' => $product->unit_price,
                'gamme' => $product->gamme?->slug,
                'type' => $product->type?->value,
            ]);
            
            return $data;
        });
    }

    public function getColumnFormats(array $options = []): array
    {
        $options = $this->getMergedOptions($options);

        if (!$options['set_euro_column']) {
            return [];
        }

        // Si on inclut l'ID, la colonne unit_price se décale de A vers D au lieu de C
        $unitPriceColumn = $options['include_id'] ? 'D' : 'C';
        
        return [$unitPriceColumn => NumberFormat::FORMAT_CURRENCY_EUR];
    }
}
