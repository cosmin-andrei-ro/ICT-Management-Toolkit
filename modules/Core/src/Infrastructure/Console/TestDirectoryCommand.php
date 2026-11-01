<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Core\Domain\Directory\Directory;
use Modules\Core\Domain\Directory\DirectoryGroupMapping;
use Modules\Core\Domain\Directory\DirectoryUnavailable;
use Modules\Core\Domain\Tenancy\OrganizationScope;

#[Signature('core:directory:test {user? : Username, UPN or email to look up}')]
#[Description('Check the Active Directory connection and, optionally, how a user would be mapped')]
final class TestDirectoryCommand extends Command
{
    public function handle(Directory $directory): int
    {
        if (! $directory->isEnabled()) {
            $this->components->warn('Active Directory sign-in is disabled (LDAP_ENABLED=false).');

            return self::SUCCESS;
        }

        $identifier = $this->argument('user');

        try {
            // A lookup forces the connection and the service account bind.
            $user = $directory->findUser(is_string($identifier) ? $identifier : '__connection_check__');
        } catch (DirectoryUnavailable $exception) {
            $this->components->error($exception->getMessage());
            $this->line('  '.($exception->getPrevious()?->getMessage() ?? ''));

            return self::FAILURE;
        }

        $this->components->info('Connected and bound with the service account.');

        if (! is_string($identifier)) {
            return self::SUCCESS;
        }

        if ($user === null) {
            $this->components->warn("No unique directory account found for [{$identifier}].");

            return self::FAILURE;
        }

        $groups = $directory->groupsOf($user);
        $mapped = DirectoryGroupMapping::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->whereIn('group_dn', $groups)
            ->with(['role', 'organization'])
            ->get();

        $this->components->twoColumnDetail('Name', $user->name);
        $this->components->twoColumnDetail('Email', $user->email);
        $this->components->twoColumnDetail('Distinguished name', $user->distinguishedName);
        $this->components->twoColumnDetail('Enabled', $user->enabled ? 'yes' : 'no');
        $this->components->twoColumnDetail('Groups (incl. nested)', (string) count($groups));

        if ($mapped->isEmpty()) {
            $this->components->warn('No group of this user is mapped: sign-in would be denied.');

            return self::SUCCESS;
        }

        $this->table(
            ['Organization', 'Role', 'Through group'],
            $mapped->map(fn (DirectoryGroupMapping $mapping): array => [
                // A soft-deleted organization no longer resolves; show its id instead.
                $mapping->organization->name ?? $mapping->organization_id,
                $mapping->role->name,
                $mapping->group_dn,
            ])->all(),
        );

        return self::SUCCESS;
    }
}
