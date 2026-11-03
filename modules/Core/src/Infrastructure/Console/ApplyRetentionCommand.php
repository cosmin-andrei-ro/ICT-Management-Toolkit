<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Console;

use Illuminate\Console\Command;
use Modules\Core\Application\Retention\ApplyRetention;

/**
 * Deletes or anonymizes what is older than each organization's retention
 * periods (ADR 0060). Scheduled every night.
 */
final class ApplyRetentionCommand extends Command
{
    protected $signature = 'core:retention';

    protected $description = 'Apply the data retention periods of every organization';

    public function handle(ApplyRetention $retention): int
    {
        $report = $retention->handle();

        foreach ($report as $organization => $counts) {
            foreach ($counts as $policy => $count) {
                $this->line("{$organization}  {$policy}: {$count}");
            }
        }

        $this->info($report === [] ? 'Nothing past its retention period.' : 'Retention applied.');

        return self::SUCCESS;
    }
}
