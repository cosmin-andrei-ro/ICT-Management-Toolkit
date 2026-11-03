<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Core\Application\Authentication\TwoFactorAuthentication;
use Modules\Core\Domain\Models\User;

#[Signature('core:account:reset-mfa {email : The account} {--force : Do not ask for confirmation}')]
#[Description('Reset the second factor of an account, for one no administrator may reset (several organizations)')]
final class ResetAccountMfaCommand extends Command
{
    public function handle(TwoFactorAuthentication $twoFactor): int
    {
        $user = User::query()->where('email', mb_strtolower((string) $this->argument('email')))->first();

        if ($user === null) {
            $this->components->error('No such user.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Reset the second factor of {$user->email}? Check first that the request really comes from them.")) {
            return self::FAILURE;
        }

        $twoFactor->resetByOperator($user);
        $this->components->info('Second factor reset. The person sets it up again at their next sign-in.');

        return self::SUCCESS;
    }
}
