<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Infrastructure\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Bia\Application\Reviews\SendCoordinatorDigest;

#[Signature('bia:digest')]
#[Description('Email continuity coordinators what is overdue, never assessed or waiting for approval')]
final class SendCoordinatorDigestCommand extends Command
{
    public function handle(SendCoordinatorDigest $digest): int
    {
        $this->components->info($digest->handle().' organization(s) emailed.');

        return self::SUCCESS;
    }
}
