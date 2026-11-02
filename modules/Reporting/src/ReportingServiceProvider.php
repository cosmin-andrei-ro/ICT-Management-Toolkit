<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Reporting;

use App\Support\Modules\ModuleServiceProvider;
use Modules\Core\Domain\Manual\Manual;
use Modules\Core\Http\Home\HomePanels;
use Modules\Reporting\Http\Livewire\Dashboard;

final class ReportingServiceProvider extends ModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'reporting';
    }

    public function register(): void
    {
        parent::register();

        $this->callAfterResolving(Manual::class, fn (Manual $manual) => $manual->addDirectory($this->modulePath('resources/manual')));
        $this->callAfterResolving(HomePanels::class, fn (HomePanels $panels) => $panels->add(Dashboard::class, 10));
    }
}
