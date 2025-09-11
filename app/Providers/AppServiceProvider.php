<?php

namespace App\Providers;

use App\Filament\Clusters\Crm\Resources\InvoiceResource\Pages\EditInvoice;
use App\Models\User;
use Illuminate\View\View;
use Filament\Tables\Table;
use App\Policies\RolePolicy;
use Filament\Support\Assets\Js;
use Filament\Support\Assets\Css;
use App\Policies\PermissionPolicy;
use Filament\Support\Colors\Color;
use Spatie\Permission\Models\Role;
use Filament\View\PanelsRenderHook;
use App\Services\MsGraph\MsgConnect;
use Illuminate\Support\Facades\Gate;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\ImageColumn;
use Spatie\Permission\Models\Permission;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Fieldset;
use Filament\Support\Facades\FilamentView;
use App\Listeners\SupplierInvoiceFileAdded;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentColor;
use Filament\Forms\Components\DateTimePicker;
use Filament\Infolists\Components\ImageEntry;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use App\Filament\Clusters\Crm\Resources\QuoteResource\Pages\EditQuote;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;



class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('msgconnect', function () {
            return new MsgConnect; // Assurez-vous que le chemin est correct
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        Gate::before(function (User $user, string $ability) {
            return $user->hasRole('admin') ? true : null;
        });
        Event::listen(MediaHasBeenAddedEvent::class, SupplierInvoiceFileAdded::class);
        Event::listen('eloquent.deleted: ' . Media::class, SupplierInvoiceFileAdded::class);
        FilamentView::registerRenderHook(
            'panels::auth.login.form.after',
            fn(): View => view('filament.hooks.login_extra')
        );
        Table::configureUsing(function (Table $table): void {
            $table
                ->paginationPageOptions([15, 25, 50, 100])
                ->defaultPaginationPageOption(25)
                ->defaultSort('updated_at', 'desc')
                ->deferFilters(false); // Préserver le comportement v3
        });

        // Préserver le comportement v3 pour la visibilité des fichiers (si vous utilisez des disques non-locaux)
        FileUpload::configureUsing(fn(FileUpload $fileUpload) => $fileUpload
            ->visibility('public'));

        ImageColumn::configureUsing(fn(ImageColumn $imageColumn) => $imageColumn
            ->visibility('public'));

        ImageEntry::configureUsing(fn(ImageEntry $imageEntry) => $imageEntry
            ->visibility('public'));

        // Préserver le comportement v3 pour les composants de layout
        Fieldset::configureUsing(fn(Fieldset $fieldset) => $fieldset
            ->columnSpanFull());

        Grid::configureUsing(fn(Grid $grid) => $grid
            ->columnSpanFull());

        Section::configureUsing(fn(Section $section) => $section
            ->columnSpanFull());
        FilamentAsset::register([
            Js::make('diff-js', 'https://cdn.jsdelivr.net/npm/diff@5.1.0/dist/diff.min.js'),
            Js::make('diff2html-js', 'https://cdn.jsdelivr.net/npm/diff2html/bundles/js/diff2html.min.js'),
            Css::make('diff2html-css', 'https://cdn.jsdelivr.net/npm/diff2html/bundles/css/diff2html.min.css'),
        ]);
        FilamentColor::register([
            'indigo' => Color::Fuchsia,
        ]);

        FilamentView::registerRenderHook(
            PanelsRenderHook::PAGE_HEADER_WIDGETS_AFTER,
            fn(): string => view('filament.hooks.two-col-open')->render(),
            // Limite aux pages concernées (IMPORTANT pour éviter d’affecter toutes les pages)
            scopes: [
                EditQuote::class,
                EditInvoice::class,
                // ajoute ici d'autres pages si besoin
            ],
        );

        // Ferme le layout + injecte l’infolist juste avant les footer widgets
        FilamentView::registerRenderHook(
            PanelsRenderHook::PAGE_FOOTER_WIDGETS_BEFORE,
            fn(): string => view('filament.hooks.two-col-close')->render(),
            scopes: [
                EditQuote::class,
                EditInvoice::class,
            ],
        );
    }
}
