<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Reporting\Infrastructure\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Reporting\Application\Actions\TakeSnapshots;

#[Signature('reporting:snapshot')]
#[Description('Record today\'s value of every indicator, for trends')]
final class TakeSnapshotsCommand extends Command
{
    public function handle(TakeSnapshots $snapshots): int
    {
        $this->components->info($snapshots->handle().' value(s) recorded.');

        return self::SUCCESS;
    }
}
