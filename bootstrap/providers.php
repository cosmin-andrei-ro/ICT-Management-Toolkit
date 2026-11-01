<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\ModulesServiceProvider;

return [
    AppServiceProvider::class,
    ModulesServiceProvider::class,
    AdminPanelProvider::class,
];
