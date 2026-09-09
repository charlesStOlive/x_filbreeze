<?php

namespace App\Services\Helpers;

use App\Enums\ProductType;
use App\Models\Company;
use App\Models\Product;
use Filament\Forms\Components\TextInput;

class ProductFormHelper
{
    public static function getPriceForCompany(Product $product, ?Company $company): float
    {
        if ($company && $product->companies->contains($company)) {
            return (float) $product->companies->firstWhere('id', $company->id)->pivot->unit_price;
        }

        return (float) $product->unit_price;
    }

    public static function getDynamicFormFields(string $type, callable $updateCallback): array
    {
        $typeEnum = ProductType::from($type);

        return match ($typeEnum) {
            ProductType::HEURES, ProductType::JOURS, ProductType::FORFAIT_U, ProductType::FORFAIT_M => [
                TextInput::make('qty')
                    ->label($typeEnum->formQtyLabel())
                    ->numeric()
                    ->suffix($typeEnum->suffix())
                    ->live(debounce: 300)
                    ->afterStateUpdatedJs(self::lineTotalUpdateJs())
                    ->afterStateUpdated($updateCallback),

                TextInput::make('cu')
                    ->label($typeEnum->formCuLabel())
                    ->numeric()
                    ->suffix('€')
                    ->live(debounce: 300)
                    ->afterStateUpdatedJs(self::lineTotalUpdateJs())
                    ->afterStateUpdated($updateCallback),

                TextInput::make('total')
                    ->label('Total')
                    ->disabled()
                    ->numeric()
                    ->dehydrated(),
            ],

            ProductType::FORFAIT_A => [
                TextInput::make('total')
                    ->label($typeEnum->formCuLabel())
                    ->numeric()
                    ->suffix($typeEnum->suffix() ?? '€')
                    ->live(onBlur: true)
                    ->dehydrated()
                    ->columnStart(3)
                    ->afterStateUpdated(function ($set, $get, $component) use ($updateCallback) {
                        $livewire = $component->getLivewire();
                        $updateCallback($set, $get, $livewire);
                    }),
            ],

            default => [],
        };
    }

    private static function lineTotalUpdateJs(): string
    {
        return <<<'JS'
            $set('total', Math.round(((Number($get('cu')) || 0) * (Number($get('qty')) || 0)) * 100) / 100)
            JS;
    }
}
