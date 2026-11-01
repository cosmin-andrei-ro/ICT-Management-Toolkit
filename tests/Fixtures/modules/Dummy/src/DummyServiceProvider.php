<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Tests\Fixtures\DummyModule;

use App\Support\Modules\ModuleServiceProvider;

final class DummyServiceProvider extends ModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'dummy';
    }
}
