<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Core\Application\Authentication\AccountLockout;
use Modules\Core\Domain\Models\User;

#[Signature('core:account:unlock {email : The account}')]
#[Description('Lift the lock of an account, for one no administrator may unlock (several organizations)')]
final class UnlockAccountCommand extends Command
{
    public function handle(AccountLockout $lockout): int
    {
        $user = User::query()->where('email', mb_strtolower((string) $this->argument('email')))->first();

        if ($user === null) {
            $this->components->error('No such user.');

            return self::FAILURE;
        }

        $lockout->unlock($user, null, 'operator');
        $this->components->info('Account unlocked.');

        return self::SUCCESS;
    }
}
