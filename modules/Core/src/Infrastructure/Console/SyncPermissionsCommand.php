<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Core\Application\Authorization\SyncPermissions;

#[Signature('core:permissions:sync')]
#[Description('Write code-defined permissions and default roles to the database')]
final class SyncPermissionsCommand extends Command
{
    public function handle(SyncPermissions $sync): int
    {
        $stale = $sync->handle();

        $this->components->info('Permissions and default roles are in sync.');

        if ($stale !== []) {
            $this->components->warn('Permissions no longer defined in code (not removed): '.implode(', ', $stale));
        }

        return self::SUCCESS;
    }
}
