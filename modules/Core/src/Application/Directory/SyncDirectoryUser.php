<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Directory;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Authorization\Role;
use Modules\Core\Domain\Directory\AuthSource;
use Modules\Core\Domain\Directory\DirectoryAccessDenied;
use Modules\Core\Domain\Directory\DirectoryGroupMapping;
use Modules\Core\Domain\Directory\DirectoryUser;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Domain\Tenancy\OrganizationScope;

/**
 * Creates or updates the local account of a directory user and makes its
 * organizations and roles match the AD group mappings (ADR 0007).
 */
final readonly class SyncDirectoryUser
{
    public function __construct(
        private ConnectionInterface $db,
        private OrganizationContext $context,
        private AuditTrail $trail,
    ) {}

    /**
     * @param  list<string>  $groups  Lowercased group DNs, including nested membership.
     *
     * @throws DirectoryAccessDenied
     */
    public function handle(DirectoryUser $directoryUser, array $groups): User
    {
        if (! $directoryUser->enabled) {
            throw new DirectoryAccessDenied(DirectoryAccessDenied::ACCOUNT_DISABLED);
        }

        $mappings = $this->mappingsFor($groups);

        if ($mappings->isEmpty()) {
            throw new DirectoryAccessDenied(DirectoryAccessDenied::NO_MAPPED_GROUP);
        }

        return $this->db->transaction(function () use ($directoryUser, $mappings): User {
            $user = $this->findOrNewUser($directoryUser);

            $user->forceFill([
                'name' => $directoryUser->name,
                'email' => $directoryUser->email,
                'directory_username' => $directoryUser->username,
                'directory_synced_at' => now(),
                'directory_disabled_at' => null,
            ])->save();

            $this->syncOrganizationsAndRoles($user, $mappings);

            return $user;
        });
    }

    /**
     * Periodic re-check of an existing directory account (core:directory:sync).
     * Unlike sign-in, a user without mapped groups is not an error here: their
     * memberships and roles are revoked.
     *
     * @param  ?DirectoryUser  $directoryUser  Null when the account no longer exists in AD.
     * @param  list<string>  $groups
     */
    public function refresh(User $user, ?DirectoryUser $directoryUser, array $groups): DirectorySyncOutcome
    {
        if ($directoryUser === null || ! $directoryUser->enabled) {
            // Roles are kept: access is blocked by the flag, and restored as is if AD re-enables the account.
            if ($user->directory_disabled_at === null) {
                $user->forceFill(['directory_disabled_at' => now()])->save();
            }

            return $directoryUser === null ? DirectorySyncOutcome::NotFound : DirectorySyncOutcome::Disabled;
        }

        return $this->db->transaction(function () use ($user, $directoryUser, $groups): DirectorySyncOutcome {
            $emailTaken = User::query()->withTrashed()
                ->where('email', $directoryUser->email)
                ->whereKeyNot($user->id)
                ->exists();

            $user->forceFill([
                'name' => $directoryUser->name,
                // A colliding email is left as it was; the next sign-in will be denied and logged.
                'email' => $emailTaken ? $user->email : $directoryUser->email,
                'directory_username' => $directoryUser->username,
                'directory_synced_at' => now(),
                'directory_disabled_at' => null,
            ])->save();

            $this->syncOrganizationsAndRoles($user, $this->mappingsFor($groups));

            return $emailTaken ? DirectorySyncOutcome::EmailConflict : DirectorySyncOutcome::Active;
        });
    }

    /**
     * @param  list<string>  $groups
     * @return Collection<int, DirectoryGroupMapping>
     */
    private function mappingsFor(array $groups): Collection
    {
        if ($groups === []) {
            return new Collection;
        }

        return DirectoryGroupMapping::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->whereIn('group_dn', $groups)
            ->with('role')
            ->get();
    }

    private function findOrNewUser(DirectoryUser $directoryUser): User
    {
        $user = User::query()->withTrashed()->where('directory_guid', $directoryUser->guid)->first();

        // Deactivated or deleted locally: an administrator's decision outranks AD.
        if ($user !== null && (! $user->is_active || $user->trashed())) {
            throw new DirectoryAccessDenied(DirectoryAccessDenied::ACCOUNT_DISABLED);
        }

        // Never take over another account that happens to share the email address.
        $emailTaken = User::query()->withTrashed()
            ->where('email', $directoryUser->email)
            ->when($user !== null, fn ($query) => $query->whereKeyNot($user?->id))
            ->exists();

        if ($emailTaken) {
            throw new DirectoryAccessDenied(DirectoryAccessDenied::EMAIL_CONFLICT);
        }

        return $user ?? (new User)->forceFill([
            'auth_source' => AuthSource::Ldap,
            'directory_guid' => $directoryUser->guid,
            'password' => null,
            'is_active' => true,
        ]);
    }

    /**
     * @param  Collection<int, DirectoryGroupMapping>  $mappings
     */
    private function syncOrganizationsAndRoles(User $user, Collection $mappings): void
    {
        $rolesByOrganization = $mappings->groupBy('organization_id');

        /** @var list<string> $previous */
        $previous = $user->organizations()->pluck('core_organizations.id')->sort()->values()->all();

        /** @var list<string> $current */
        $current = $rolesByOrganization->keys()->sort()->values()->all();

        // Roles are team-scoped: revoke them in organizations the user has left.
        foreach (array_diff($previous, $current) as $organizationId) {
            $this->context->runAs($organizationId, fn () => $this->syncRoles($user, []));
        }

        $user->organizations()->sync($current);

        foreach ($rolesByOrganization as $organizationId => $organizationMappings) {
            /** @var list<Role> $roles */
            $roles = $organizationMappings->pluck('role')->unique('id')->values()->all();

            $this->context->runAs((string) $organizationId, fn () => $this->syncRoles($user, $roles));
        }

        if ($previous !== $current) {
            // Membership is a pivot write, invisible to the model observer.
            $this->trail->record(AuditEvent::Updated, $user, ['organizations' => $previous], ['organizations' => $current]);
        }

        if (! in_array($user->current_organization_id, $current, true)) {
            $user->forceFill(['current_organization_id' => $current[0] ?? null])->save();
        }
    }

    /**
     * Applies only the difference within the current organization. Role::syncRoles()
     * detaches and re-attaches everything, which would log on every sign-in.
     *
     * @param  list<Role>  $roles
     */
    private function syncRoles(User $user, array $roles): void
    {
        $user->unsetRelation('roles');

        /** @var list<string> $currentIds */
        $currentIds = $user->roles()->pluck('core_roles.id')->all();
        $desiredIds = array_map(fn (Role $role): string => $role->id, $roles);

        $toAssign = array_values(array_filter($roles, fn (Role $role): bool => ! in_array($role->id, $currentIds, true)));
        $toRemove = array_values(array_diff($currentIds, $desiredIds));

        if ($toAssign !== []) {
            $user->assignRole($toAssign);
        }

        foreach ($toRemove as $roleId) {
            $user->removeRole($roleId);
        }
    }
}
