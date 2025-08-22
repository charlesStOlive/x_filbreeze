<?php

namespace App\Providers;

use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;

class TreeAssetsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        FilamentAsset::register([
            Css::make('filament-tree-min', __DIR__ . '/../../resources/dist/filament-tree.css'),
        ], 'app/filament-tree');

        FilamentAsset::register([
            AlpineComponent::make('filament-tree-component', __DIR__ . '/../../resources/dist/components/filament-tree-component.js'),
        ], 'app/filament-tree');
    }
}
