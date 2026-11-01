<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Modules\Core\Application\Setup\SetUpInstance;

#[Signature('core:setup')]
#[Description('Create the first organization and its administrator (only on a new installation)')]
final class SetupCommand extends Command
{
    public function handle(SetUpInstance $setUp): int
    {
        if ($setUp->alreadySetUp()) {
            $this->components->error('This installation already has an administrator. Manage people from the application.');

            return self::FAILURE;
        }

        $organization = self::text($this->ask('Organization name'));
        $name = self::text($this->ask('Administrator full name'));
        $email = self::text($this->ask('Administrator email (used to sign in)'));
        $password = self::text($this->secret('Password (at least 12 characters)'));

        if ($password !== self::text($this->secret('Repeat the password'))) {
            $this->components->error('The passwords do not match.');

            return self::FAILURE;
        }

        try {
            $user = $setUp->handle($organization, $name, $email, $password);
        } catch (ValidationException $exception) {
            foreach ($exception->validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $this->components->info("Done. Sign in as {$user->email}.");

        return self::SUCCESS;
    }

    private static function text(mixed $answer): string
    {
        return is_string($answer) ? $answer : '';
    }
}
