<?php

namespace App\Services\Exports;

use App\Models\Product;
use App\Enums\ProductType;
use App\Models\Company;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

class CompanyProductsExporter extends Exporter
{
    protected static ?string $model = Product::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('id')->enabledByDefault(false),
            ExportColumn::make('code')->label('code')->enabledByDefault(true),
            ExportColumn::make('title')->label('title')->enabledByDefault(false),
            ExportColumn::make('type')->label('type')->enabledByDefault(false)
                ->formatStateUsing(
                    fn($state) =>
                    is_string($state) ? $state : $state?->value
                ),
            ExportColumn::make('gamme')->label('gamme')->enabledByDefault(false),
            // Prix personnalisé dans la table pivot (datasets_company_product.unit_price)
            // Automatiquement exposé sous la forme "pivot_unit_price" par Filament
            ExportColumn::make('pivot_unit_price')
                ->label('unit_price')->enabledByDefault(true),
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

    public static function getQuery(): Builder
    {
        $companyId = request()->route('record'); // utilisé depuis l’action dans CompanyResource
        $company = Company::findOrFail($companyId);

        return $company->products()->getQuery(); // BelongsToMany query (avec pivot)
    }
}
