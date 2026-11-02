<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Modules\Core\Http\Filament\Support\InitialsAvatarProvider;
use Modules\Core\Http\Middleware\AllowUnsafeEval;
use Modules\Core\Http\Middleware\RequireTwoFactor;
use Modules\Core\Http\Middleware\SetCurrentOrganization;
use ReflectionClass;

/**
 * Administration panel shared by all modules (ADR 0003). Sign-in goes through the
 * application's own login; access requires the core.admin.access permission.
 */
final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel
            ->default()
            ->id('admin')
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->path('admin')
            ->colors([
                'primary' => Color::Slate,
            ])
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                AccountWidget::class,
            ])
            ->navigationItems([
                NavigationItem::make(fn (): string => __('core::manual.title'))
                    ->url(fn (): string => route('manual.index'), shouldOpenInNewTab: true)
                    ->icon(Heroicon::OutlinedBookOpen)
                    ->sort(1000),
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
                // Filament renders inline Alpine expressions, which need eval.
                AllowUnsafeEval::class,
                // Before Authenticate: canAccessPanel() checks an organization-scoped permission.
                SetCurrentOrganization::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                RequireTwoFactor::class,
            ], isPersistent: true);

        // After id(): resource discovery keys its cache by panel id.
        $this->discoverModuleResources($panel);

        return $panel;
    }

    /**
     * Each enabled module may ship resources and pages in src/Http/Filament/{Resources,Pages}.
     */
    private function discoverModuleResources(Panel $panel): void
    {
        /** @var array<string, array{provider: class-string, enabled: bool}> $modules */
        $modules = config('modules.modules', []);

        foreach ($modules as $module) {
            if (! $module['enabled']) {
                continue;
            }

            $provider = new ReflectionClass($module['provider']);
            $base = dirname((string) $provider->getFileName()).'/Http/Filament';
            $namespace = $provider->getNamespaceName().'\\Http\\Filament';

            if (is_dir($base.'/Resources')) {
                $panel->discoverResources(in: $base.'/Resources', for: $namespace.'\\Resources');
            }

            if (is_dir($base.'/Pages')) {
                $panel->discoverPages(in: $base.'/Pages', for: $namespace.'\\Pages');
            }
        }
    }
}
