<?php

namespace App\Providers;

use App\Listeners\SupplierInvoiceFileAdded;
use App\Models\Company;
use App\Policies\CompanyPolicy;
use Filament\Forms\Components\FileUpload;
use Filament\Infolists\Components\ImageEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentColor;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Application services (les services Infrastructure sont maintenant dans le package msgraph-filament)
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Company::class, CompanyPolicy::class);
        // Event déplacé dans le workflow des states (DraftToValidated->handle())
        // Event::listen(MediaHasBeenAddedEvent::class, SupplierInvoiceFileAdded::class);
        // Event::listen('eloquent.deleted: ' . Media::class, SupplierInvoiceFileAdded::class);
        FilamentView::registerRenderHook(
            'panels::auth.login.form.after',
            fn (): View => view('filament.hooks.login_extra')
        );
        Table::configureUsing(function (Table $table): void {
            $table
                ->paginationPageOptions([15, 25, 50, 100])
                ->defaultPaginationPageOption(25)
                ->defaultSort('updated_at', 'desc')
                ->deferFilters(false); // Préserver le comportement v3
        });

        // Préserver le comportement v3 pour la visibilité des fichiers (si vous utilisez des disques non-locaux)
        FileUpload::configureUsing(fn (FileUpload $fileUpload) => $fileUpload
            ->visibility('public'));

        ImageColumn::configureUsing(fn (ImageColumn $imageColumn) => $imageColumn
            ->visibility('public'));

        ImageEntry::configureUsing(fn (ImageEntry $imageEntry) => $imageEntry
            ->visibility('public'));

        // Préserver le comportement v3 pour les composants de layout
        Fieldset::configureUsing(fn (Fieldset $fieldset) => $fieldset
            ->columnSpanFull());

        Grid::configureUsing(fn (Grid $grid) => $grid
            ->columnSpanFull());

        Section::configureUsing(fn (Section $section) => $section
            ->columnSpanFull());
        FilamentAsset::register([
            AlpineComponent::make('mermaid-diagram', __DIR__.'/../../resources/js/dist/components/mermaid-diagram.js')->loadedOnRequest(),
        ]);
        FilamentColor::register([
            'indigo' => Color::Fuchsia,
        ]);
    }
}
