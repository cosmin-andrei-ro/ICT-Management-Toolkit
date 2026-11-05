<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Pam\Infrastructure\Console;

use Illuminate\Console\Command;
use Modules\Pam\Application\PamVault;

/**
 * Prints a new vault key to put in PAM_KEY. It changes nothing by itself.
 */
final class GenerateVaultKeyCommand extends Command
{
    protected $signature = 'pam:key';

    protected $description = 'Print a new key for the privileged access vault';

    public function handle(): int
    {
        $this->line(PamVault::generateKey());

        return self::SUCCESS;
    }
}
