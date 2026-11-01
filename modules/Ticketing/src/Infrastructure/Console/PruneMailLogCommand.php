<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Infrastructure\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Ticketing\Application\Email\PruneInboundEmails;

#[Signature('ticketing:mail:prune')]
#[Description('Delete entries of the incoming email log older than the retention period')]
final class PruneMailLogCommand extends Command
{
    public function handle(PruneInboundEmails $prune): int
    {
        $this->components->info($prune->handle().' log entr(y/ies) deleted.');

        return self::SUCCESS;
    }
}
