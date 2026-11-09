<?php

namespace App\Providers\Filament;

use Filament\Auth\Pages\Login;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $skipAuthenticationForLocalTesting = app()->environment('local') && (bool) config('app.debug');

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->sidebarFullyCollapsibleOnDesktop()
            ->viteTheme('resources/css/admin.css')
            ->brandName('TRANSPORTES ENRIQUES')
            ->brandLogo(asset('images/enriques-logo.jpeg'))
            ->darkModeBrandLogo(asset('images/enriques-logo-dark.png'))
            ->brandLogoHeight('3.5rem')
            ->favicon(asset('images/enriques-mark.png'))
            ->login($skipAuthenticationForLocalTesting ? null : Login::class)
            ->colors([
                'primary' => Color::hex('#e30613'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
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
            ->authMiddleware($skipAuthenticationForLocalTesting ? [] : [Authenticate::class]);
    }
}
