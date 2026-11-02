<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Contracts\View\View;
use Modules\Core\Http\Home\HomePanels;

final readonly class HomeController
{
    public function __construct(private HomePanels $panels) {}

    public function __invoke(): View
    {
        return view('core::home', ['panels' => $this->panels->all()]);
    }
}
