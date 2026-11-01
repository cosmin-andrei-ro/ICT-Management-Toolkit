<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Modules\Core\CoreServiceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Application Modules
    |--------------------------------------------------------------------------
    |
    | Each module is registered through its own service provider, which must
    | extend App\Support\Modules\ModuleServiceProvider. A module listed here
    | is booted only when its flag is true, so a module can be switched off
    | per installation without touching code. Core must stay enabled:
    | every other module depends on it.
    |
    */

    'modules' => [
        'core' => [
            'provider' => CoreServiceProvider::class,
            'enabled' => true,
        ],
    ],

];
