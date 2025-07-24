<?php

namespace App\Services\Exports\Company;

use App\Models\Company;
use App\Models\Product;
use App\Enums\ProductType;
use Illuminate\Support\Collection;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Toggle;
use Filament\Actions\Exports\Exporter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\Exports\ExportColumn;
use App\Services\Exports\BaseExcelTemplate;
use Filament\Actions\Exports\Models\Export;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use App\Services\Document\Contracts\DocumentProducer;

class CompanyProductsExporter extends BaseExcelTemplate implements DocumentProducer
{
    protected static ?string $model = Product::class;

    public function __construct(protected mixed $record = null) {}

    public function getColumns(): array
    {
        return [
            'code' => 'Code',
            'title' => 'Titre',
            'unit_price' => 'Prix Unitaire',
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'L\'export des produits du client est terminé : ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exportés.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' en échec.';
        }

        return $body;
    }

    public static function getDefaultOptions(): array
    {
        return [
            'show_only_company_product' => false,
            'set_euro_column' => true,
        ];
    }

    public function getForm(): array
    {
        return [
            Group::make([
                Toggle::make('show_only_company_product')
                    ->label('Afficher uniquement les produits liés à la société')
                    ->default($this->options['show_only_company_product'] ?? false),
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
        $columns = $this->getColumns();

        $showOnlyCompany = $options['show_only_company_product'] ?? false;

        $company = $this->record;

        $companyProducts = $company->products()
            ->withPivot(['unit_price'])
            ->get()
            ->keyBy('id');

        if ($showOnlyCompany) {
            // ✅ Cas : uniquement les produits du pivot (spécifiques à la company)
            return $companyProducts->map(function ($product) {
                return [
                    'code' => $product->code,
                    'title' => $product->title,
                    'unit_price' => $product->pivot->unit_price ?? $product->unit_price,
                ];
            })->values();
        }

        // ✅ Cas : tous les produits, mais avec override si pivot existe
        $products = \App\Models\Product::all()->keyBy('id');

        return $products->map(function ($product) use ($companyProducts) {
            $pivot = $companyProducts->get($product->id);

            return [
                'code' => $product->code,
                'title' => $product->title,
                'unit_price' => $pivot?->pivot->unit_price ?: $product->unit_price,
            ];
        })->values();
    }

    public function getColumnFormats(array $options = []): array
    {
        \Log::info('getColumnFormats called with options: ',  $options);
        return  $options['set_euro_column'] ?? true
            ? ['C' => NumberFormat::FORMAT_CURRENCY_EUR]
            : [];
    }
}
