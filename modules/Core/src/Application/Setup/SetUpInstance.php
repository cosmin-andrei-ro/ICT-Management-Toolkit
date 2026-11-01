<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Setup;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use LogicException;
use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Spatie\Permission\Models\Role;

/**
 * First run of a new installation: the first organization and its administrator
 * (a local account). Refused once any administrator exists, so it cannot be used
 * to take over a running instance.
 */
final readonly class SetUpInstance
{
    public function __construct(
        private ConnectionInterface $db,
        private SyncPermissions $syncPermissions,
        private OrganizationContext $context,
    ) {}

    public function alreadySetUp(): bool
    {
        return Role::query()->where('name', DefaultRole::Administrator->value)->whereHas('users')->exists();
    }

    /**
     * @throws ValidationException
     */
    public function handle(string $organizationName, string $name, string $email, string $password): User
    {
        if ($this->alreadySetUp()) {
            throw new LogicException('This installation already has an administrator.');
        }

        $data = Validator::make(
            ['organization' => $organizationName, 'name' => $name, 'email' => mb_strtolower(trim($email)), 'password' => $password],
            [
                'organization' => ['required', 'string', 'max:200'],
                'name' => ['required', 'string', 'max:200'],
                'email' => ['required', 'email', 'max:255'],
                'password' => ['required', Password::defaults()],
            ],
        )->validate();

        return $this->db->transaction(function () use ($data, $organizationName): User {
            $this->syncPermissions->handle();

            $organization = Organization::query()->create([
                'name' => $data['organization'],
                'slug' => Str::slug($organizationName) ?: 'organization',
            ]);

            $user = new User;
            $user->forceFill([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'current_organization_id' => $organization->id,
            ])->save();
            $user->organizations()->attach($organization);

            $this->context->runAs($organization, fn () => $user->assignRole(DefaultRole::Administrator->value));

            return $user;
        });
    }
}
