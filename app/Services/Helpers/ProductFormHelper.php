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

    public static function getDynamicFormFields(string $type): array
    {
        $typeEnum = ProductType::from($type);

        return match ($typeEnum) {
            ProductType::HEURES => [
                TextInput::make('qty')
                    ->label('Nombre d\'heures')
                    ->numeric()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn(callable $set, callable $get, $livewire) =>
                        \App\Filament\Clusters\Crm\Resources\InvoiceResource::updateProductTotal($set, $get, $livewire)),

                TextInput::make('cu')
                    ->label('Coût par heure')
                    ->numeric()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn(callable $set, callable $get, $livewire) =>
                        \App\Filament\Clusters\Crm\Resources\InvoiceResource::updateProductTotal($set, $get, $livewire)),

                TextInput::make('total')
                    ->label('Total')
                    ->disabled()
                    ->numeric()
                    ->dehydrated(),
            ],

            ProductType::JOURS => [
                TextInput::make('qty')
                    ->label('Nombre de jours')
                    ->numeric()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn(callable $set, callable $get, $livewire) =>
                        \App\Filament\Clusters\Crm\Resources\InvoiceResource::updateProductTotal($set, $get, $livewire)),

                TextInput::make('cu')
                    ->label('Coût par jour')
                    ->numeric()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn(callable $set, callable $get, $livewire) =>
                        \App\Filament\Clusters\Crm\Resources\InvoiceResource::updateProductTotal($set, $get, $livewire)),

                TextInput::make('total')
                    ->label('Total')
                    ->disabled()
                    ->numeric()
                    ->dehydrated(),
            ],

            ProductType::FORFAIT_U => [
                TextInput::make('cu')
                    ->label('Montant forfaitaire')
                    ->numeric()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn(callable $set, callable $get, $livewire) =>
                        \App\Filament\Clusters\Crm\Resources\InvoiceResource::updateProductTotal($set, $get, $livewire)),

                TextInput::make('total')
                    ->label('Total')
                    ->disabled()
                    ->numeric()
                    ->dehydrated(),
            ],

            default => [],
        };
    }
}