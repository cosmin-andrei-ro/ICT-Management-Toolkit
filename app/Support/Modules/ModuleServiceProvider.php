<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace App\Support\Modules;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;

/**
 * Base provider for a module under modules/<Name>/.
 *
 * A module provider lives in modules/<Name>/src/, so the module root is one
 * level above it. Resources are loaded only when the conventional path exists,
 * which lets a module ship just the pieces it needs.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Short lowercase key, used as the view and translation namespace
     * (e.g. "ticketing" → view('ticketing::…'), __('ticketing::…')).
     */
    abstract public function moduleKey(): string;

    public function boot(): void
    {
        $root = $this->modulePath();

        if (is_dir($root.'/database/migrations')) {
            $this->loadMigrationsFrom($root.'/database/migrations');
        }

        if (is_dir($root.'/resources/views')) {
            $this->loadViewsFrom($root.'/resources/views', $this->moduleKey());
        }

        if (is_dir($root.'/resources/lang')) {
            $this->loadTranslationsFrom($root.'/resources/lang', $this->moduleKey());
        }

        if (! $this->app->routesAreCached()) {
            $this->loadModuleRoutes($root.'/routes');
        }
    }

    protected function modulePath(string $path = ''): string
    {
        $providerFile = (string) (new ReflectionClass($this))->getFileName();
        $root = dirname($providerFile, 2);

        return $path === '' ? $root : $root.'/'.ltrim($path, '/');
    }

    private function loadModuleRoutes(string $routesPath): void
    {
        if (is_file($routesPath.'/web.php')) {
            Route::middleware('web')->group($routesPath.'/web.php');
        }

        if (is_file($routesPath.'/api.php')) {
            Route::middleware('api')->prefix('api/v1')->group($routesPath.'/api.php');
        }
    }
}
