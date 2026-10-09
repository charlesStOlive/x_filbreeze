<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Guava\FilamentKnowledgeBase\Plugins\KnowledgeBasePlugin;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Le centre d'aide (/aide) : la base de connaissances Guava, lue dans docs/knowledge-base/{locale}. Les packages y
 * publient leurs pages (`vendor:publish --tag=filament-tma-docs`…) ; l'admin les ouvre par le « ? » de ses écrans
 * (KnowledgeBaseCompanionPlugin). Entrée : `panels.knowledge-base.access`.
 */
class KnowledgeBasePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('knowledge-base')
            ->path('aide')
            ->brandName('Centre d’aide')
            ->brandLogo(asset('images/logo.png'))
            ->brandLogoHeight('2rem')
            ->login()
            ->plugin(
                KnowledgeBasePlugin::make(base_path('docs/knowledge-base'))
                    ->backUrl(fn (): string => filament()->getPanel('admin')->getUrl()),
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            // Les pages se citent par « #modal-<page> » : dans l'admin, la page s'ouvre dans une fenêtre ; ici, le lien y mène.
            ->renderHook(PanelsRenderHook::BODY_END, fn (): View => view('filament.knowledge-base.modal-links'))
            ->viteTheme('resources/css/filament/admin/filament.css');
    }
}
