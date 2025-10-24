<?php

namespace App\Providers\Filament;

use Filament\Pages;
use Filament\Panel;
use Filament\Widgets;
use Filament\PanelProvider;
use Filament\Actions\Action;


use Filament\Support\Colors\Color;
use App\Filament\Pages\UserSettings;
use Filament\Http\Middleware\Authenticate;
use Pboivin\FilamentPeek\FilamentPeekPlugin;
use CharlesStOlive\FilamentStateFusionEnhanced\FilamentStateFusionEnhancedPlugin;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Filament\Support\Enums\Width;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandLogo(asset('images/logo.png'))
            ->darkModeBrandLogo(asset('images/logo white.png'))
            ->brandLogoHeight('4rem')
            ->databaseNotifications()
            ->userMenuItems([
                Action::make('user_settings')
                    ->label('Mes options')
                    ->url(fn() => UserSettings::getUrl())
                    ->icon('heroicon-o-cog-6-tooth'),
            ])
            ->plugins([
                FilamentPeekPlugin::make()->disablePluginStyles(),
                FilamentStateFusionEnhancedPlugin::make(),
            ])
            ->colors([
                'primary' => '#DB8E57',
                'secondary' => '#55cec4',
                'success' => '#33c233',
                'error' => '#972121',
                'warning' => '#b26e2b',
                'info' => '#3e79b4',
            ])
            ->maxContentWidth(Width::Full)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\\Filament\\Clusters')
            ->pages([
                Pages\Dashboard::class,
            ])


            ->widgets([
                Widgets\AccountWidget::class,
                // Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->viteTheme('resources/css/filament/admin/filament.css');
    }
}
