<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
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
    /**
     * `Color::hex('#FAC902')`'s auto-generated palette fails WCAG AA text
     * contrast against white for every shade Tailwind conventions
     * typically use as text (50 through 600 all fail; verified with
     * Filament's own `Color::calculateContrastRatio()` — shade 600 comes
     * out at 3.92:1 against white, need 4.5:1). Real bright yellow simply
     * cannot pass at those weights against a light surface — mirrors the
     * exact bug fixed in the Flutter app's own theme (CLAUDE.md: "Yellow
     * is a highlight, not a background wash"). Filament v5 does have a
     * contrast-aware `findShade()`/`ComponentColorMap` mechanism some
     * built-in components use to pick a passing shade automatically, but
     * not every Blade template in the ecosystem is guaranteed to route
     * through it, so this palette is built to be safe by construction
     * rather than by hoping every consumer opts in: 50-500 stay the true
     * brand yellow (verified 500 = exactly #FAC902) for anywhere it's
     * used as a background/accent (buttons, badges, active-state fills —
     * all paired with dark text/icons on top, never yellow-on-light
     * text), and 600-950 blend toward Sokoni near-black `#0A0A0A` instead
     * of Filament's default same-hue darken, so any shade used as *text*
     * reads dark and passes AA (600 itself verified at 6.09:1 against
     * white, every shade above it higher still).
     *
     * @return array<int, string>
     */
    protected function sokoniYellowPalette(): array
    {
        return [
            50 => '#FFFCF2',
            100 => '#FEF7D9',
            200 => '#FDECA6',
            300 => '#FCDF67',
            400 => '#FBD128',
            500 => '#FAC902',
            600 => '#766006',
            700 => '#4D3F08',
            800 => '#332A09',
            900 => '#201B09',
            950 => '#14120A',
        ];
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('Sokoni Admin')
            ->brandLogo(asset('images/brand/sokoni_logo.png'))
            ->brandLogoHeight('2rem')
            ->favicon(asset('images/brand/sokoni_logo_icon.png'))
            ->login()
            ->colors([
                'primary' => $this->sokoniYellowPalette(),
                'danger' => Color::hex('#E5484D'),
                'success' => Color::hex('#12A150'),
                // Filament's own default 'gray' (Color::Gray[950] =
                // #030712) is blue-tinted, not neutral — clashes visibly
                // next to a warm yellow accent. Color::Neutral[950] is
                // #0a0a0a, an exact match for the near-black surface
                // CLAUDE.md specifies for dark mode throughout the
                // Flutter app, so dark-mode panel surfaces are now
                // genuinely on-brand rather than an arbitrary dark grey.
                'gray' => Color::Neutral,
            ])
            // Explicit order — Filament falls back to alphabetical
            // otherwise, which would scramble the intended
            // Overview → Catalog → Commerce → Community → Trust & Safety →
            // System flow (an operator's rough order of "how often I look
            // at this").
            ->navigationGroups([
                'Overview',
                'Catalog',
                'Commerce',
                'Community',
                'Trust & Safety',
                'System',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
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
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
