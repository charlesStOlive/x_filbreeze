<?php

namespace App\Services\Helpers;

use App\Models\Company;
use App\Models\Product;
use App\Enums\ProductType;
use Filament\Forms\Components\TextInput;
use App\Filament\Clusters\Crm\Resources\InvoiceResource;

class ProductFormHelper
{
    public static function getPriceForCompany(Product $product, ?Company $company): float
    {
        if ($company && $product->companies->contains($company)) {
            return (float) $product->companies->firstWhere('id', $company->id)->pivot->unit_price;
        }

        return (float) $product->unit_price;
    }

    public static function getDynamicFormFields(string $type): array
    {
        $typeEnum = ProductType::from($type);

        return match ($typeEnum) {
            ProductType::HEURES, ProductType::JOURS => [
                TextInput::make('qty')
                    ->label($typeEnum->formQtyLabel())
                    ->numeric()
                    ->suffix($typeEnum->suffix())
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($set, $get, $livewire) {
                        InvoiceResource::updateProductTotal($set, $get, $livewire);
                    }),

                TextInput::make('cu')
                    ->label($typeEnum->formCuLabel())
                    ->numeric()
                    ->suffix('€')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($set, $get, $livewire) {
                        InvoiceResource::updateProductTotal($set, $get, $livewire);
                    }),

                TextInput::make('total')
                    ->label('Total')
                    ->disabled()
                    ->numeric()
                    ->dehydrated(),
            ],

            ProductType::FORFAIT_U, ProductType::FORFAIT_M, ProductType::FORFAIT_A => [
                TextInput::make('total')
                    ->label($typeEnum->formCuLabel())
                    ->numeric()
                    ->suffix($typeEnum->suffix() ?? '€')
                    ->live(onBlur: true)
                    ->dehydrated()
                    ->columnStart(3)
                    ->afterStateUpdated(function ($set, $get, $component) {
                        $livewire = $component->getLivewire();
                        \App\Filament\Clusters\Crm\Resources\InvoiceResource::updateItemsTotal($set, $get, $livewire);
                    }),
            ],

            default => [],
        };
    }
}
