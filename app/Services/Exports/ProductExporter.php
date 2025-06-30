<?php

namespace App\Services\Exports;

use App\Models\Product;
use App\Enums\ProductType;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;

class ProductExporter extends Exporter
{
    protected static ?string $model = Product::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('id'),

            ExportColumn::make('code')
                ->label('code'),

            ExportColumn::make('title')
                ->label('title'),

            ExportColumn::make('type')
                ->label('type')
                ->formatStateUsing(function (ProductType|string|null $state) {
                    return is_string($state) ? $state : $state?->value;
                }),

            ExportColumn::make('gamme')
                ->label('gamme'),

            ExportColumn::make('unit_price')
                ->label('unit_price')
                ->formatStateUsing(fn ($state) => (float) $state)
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'L\'export des produits est terminé ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exportés.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
