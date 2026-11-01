<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

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
use Modules\Core\Http\Middleware\AllowUnsafeEval;
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
            ]);

        // After id(): resource discovery keys its cache by panel id.
        $this->discoverModuleResources($panel);

        return $panel;
    }

    /**
     * Each enabled module may ship resources in src/Http/Filament/Resources.
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
            $directory = dirname((string) $provider->getFileName()).'/Http/Filament/Resources';

            if (is_dir($directory)) {
                $panel->discoverResources(in: $directory, for: $provider->getNamespaceName().'\\Http\\Filament\\Resources');
            }
        }
    }
}
