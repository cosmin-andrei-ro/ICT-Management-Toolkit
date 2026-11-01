<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Core\Application\Exports\PruneExports;

#[Signature('core:exports:prune')]
#[Description('Delete expired exports and their files')]
final class PruneExportsCommand extends Command
{
    public function handle(PruneExports $prune): int
    {
        $this->components->info($prune->handle().' export(s) deleted.');

        return self::SUCCESS;
    }
}
