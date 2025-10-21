<?php

namespace App\Services\MaatExports\Templates\Company;

use Filament\Schemas\Components\Group;
use App\Models\Product;
use App\Models\Company;
use Illuminate\Support\Collection;
use Filament\Forms\Components\Toggle;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use App\Services\MaatExports\Base\BaseExcelTemplate;

class CompanyProductsExporter extends BaseExcelTemplate
{
    public static function key(): string
    {
        return 'company_products';
    }

    public static function label(): string
    {
        return 'Produits de l’entreprise';
    }

    public static function getDefaultOptions(): array
    {
        return [
            'set_euro_column' => true,
            'show_only_company_product' => false,
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
                    ->default($this->getOption('set_euro_column')),

                Toggle::make('show_only_company_product')
                    ->label('Afficher uniquement les produits liés à l’entreprise')
                    ->default($this->getOption('show_only_company_product')),
            ]),
        ];
    }

    public function getFileName(array $options = []): string
    {
        $company = $this->getRecord();
        return 'produits_' . ($company?->slug ?? 'inconnu');
    }

    public function getData(array $options = []): Collection
    {
        $options = $this->getMergedOptions($options);
        $company = $this->getRecord();

        if (! $company instanceof Company) {
            return collect();
        }

        $showOnlyCompany = $options['show_only_company_product'] ?? false;

        $companyProducts = $company->products()
            ->withPivot(['unit_price'])
            ->get()
            ->keyBy('id');

        if ($showOnlyCompany) {
            // ✅ Seulement les produits liés à l’entreprise (via pivot)
            return $companyProducts->map(function ($product) {
                return [
                    'code' => $product->code,
                    'title' => $product->title,
                    'gamme' => $product->gamme?->slug,
                    'type' => $product->type?->value,
                    'unit_price' => $product->pivot->unit_price ?? $product->unit_price,
                ];
            })->values();
        }

        // ✅ Tous les produits, en remplaçant le prix si un pivot existe
        $products = Product::all()->keyBy('id');

        return $products->map(function ($product) use ($companyProducts) {
            $pivotProduct = $companyProducts->get($product->id);

            return [
                'code' => $product->code,
                'title' => $product->title,
                'gamme' => $product->gamme?->slug,
                'type' => $product->type?->value,
                'unit_price' => $pivotProduct?->pivot->unit_price ?? $product->unit_price,
            ];
        })->values();
    }

    public function getColumnFormats(array $options = []): array
    {
        $options = $this->getMergedOptions($options);


        return $options['set_euro_column']
            ? ['C' => NumberFormat::FORMAT_CURRENCY_EUR]
            : [];
    }
}
