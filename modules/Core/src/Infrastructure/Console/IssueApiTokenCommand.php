<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Modules\Core\Application\Api\ApiAbility;
use Modules\Core\Application\Api\IssueApiToken;
use Modules\Core\Domain\Models\User;

#[Signature('core:api-token {email : The user the token acts as} {name : What the token is for} {--ability=* : Abilities to grant} {--days= : Lifetime in days}')]
#[Description('Issue an API token for a user (shown once)')]
final class IssueApiTokenCommand extends Command
{
    public function handle(IssueApiToken $issue): int
    {
        $user = User::query()->where('email', mb_strtolower((string) $this->argument('email')))->first();

        if ($user === null) {
            $this->components->error('No such user.');

            return self::FAILURE;
        }

        /** @var list<string> $abilities */
        $abilities = array_values(array_filter((array) $this->option('ability'), 'is_string'));
        $days = $this->option('days');

        try {
            $token = $issue->handle($user, (string) $this->argument('name'), $abilities, is_numeric($days) ? now()->addDays((int) $days) : null);
        } catch (InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());
            $this->line('  Available abilities: '.implode(', ', ApiAbility::all()));

            return self::FAILURE;
        }

        $this->components->info('Token created. Copy it now: it will not be shown again.');
        $this->line($token->plainTextToken);

        return self::SUCCESS;
    }
}
