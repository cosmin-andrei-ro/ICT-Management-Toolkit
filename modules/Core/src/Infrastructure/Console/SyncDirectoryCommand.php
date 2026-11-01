<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Core\Application\Directory\DirectorySyncAborted;
use Modules\Core\Application\Directory\SyncDirectoryAccounts;
use Modules\Core\Domain\Directory\DirectoryUnavailable;

#[Signature('core:directory:sync {--force : Apply even if most accounts appear to be missing from AD}')]
#[Description('Re-check Active Directory accounts: block disabled or removed ones and re-sync roles')]
final class SyncDirectoryCommand extends Command
{
    public function handle(SyncDirectoryAccounts $sync): int
    {
        try {
            $summary = $sync->handle((bool) $this->option('force'));
        } catch (DirectoryUnavailable|DirectorySyncAborted $exception) {
            report($exception);
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($summary as $outcome => $count) {
            $this->components->twoColumnDetail(str_replace('_', ' ', ucfirst($outcome)), (string) $count);
        }

        return self::SUCCESS;
    }
}
