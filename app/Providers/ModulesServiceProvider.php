<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace App\Providers;

use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Support\ServiceProvider;
use LogicException;

final class ModulesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /** @var array<string, array{provider: class-string, enabled: bool}> $modules */
        $modules = $this->app->make('config')->get('modules.modules', []);

        foreach ($modules as $key => $module) {
            if (! $module['enabled']) {
                continue;
            }

            // Fail loudly: a misconfigured module would otherwise silently skip its policies.
            if (! is_subclass_of($module['provider'], ModuleServiceProvider::class)) {
                throw new LogicException("Module [{$key}] provider must extend ".ModuleServiceProvider::class.'.');
            }

            $this->app->register($module['provider']);
        }
    }
}
