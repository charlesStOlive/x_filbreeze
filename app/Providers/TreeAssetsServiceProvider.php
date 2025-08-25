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
            Css::make('filament-tree-modern', __DIR__ . '/../../resources/css/dist/tree-component.css'),
        ], 'app/filament-tree');

        FilamentAsset::register([
            AlpineComponent::make('filament-tree-component', __DIR__ . '/../../resources/js/dist/components/filament-tree-component.js'),
        ], 'app/filament-tree');
    }
}
