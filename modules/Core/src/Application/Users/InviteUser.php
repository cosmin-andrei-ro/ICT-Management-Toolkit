<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Users;

use Illuminate\Contracts\Auth\PasswordBrokerFactory;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Core\Application\Notifications\PasswordLink;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Authorization\Role;
use Modules\Core\Domain\Directory\AuthSource;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Adds a person to the current organization. A new local account gets an
 * invitation link to choose its own password: administrators never know it.
 * Someone who already has an account (another organization, or AD) is added
 * to this one, without an email.
 */
final readonly class InviteUser
{
    public function __construct(
        private ConnectionInterface $db,
        private OrganizationContext $context,
        private PasswordBrokerFactory $brokers,
        private AuditTrail $trail,
    ) {}

    /**
     * @param  list<string>  $roleIds  Roles available to the current organization.
     * @return bool Whether an invitation was sent.
     */
    public function handle(string $name, string $email, array $roleIds, User $actor): bool
    {
        $organizationId = $this->context->requireId();
        $email = mb_strtolower(trim($email));
        $roles = $this->assignableRoles($roleIds, $organizationId);

        [$user, $isNew] = $this->db->transaction(function () use ($name, $email, $roles, $organizationId): array {
            $user = User::query()->where('email', $email)->first();
            $isNew = $user === null;

            if ($user === null) {
                $user = new User;
                $user->forceFill([
                    'name' => trim($name),
                    'email' => $email,
                    'auth_source' => AuthSource::Local,
                    // Unusable until the invitation is accepted.
                    'password' => Str::random(64),
                    'current_organization_id' => $organizationId,
                ])->save();
            }

            if (! $user->isMemberOf($organizationId)) {
                $user->organizations()->attach($organizationId);
            }

            $this->context->runAs($organizationId, fn () => $user->assignRole($roles));

            return [$user, $isNew];
        });

        if ($isNew) {
            $this->sendInvitation($user, $actor);
        }

        return $isNew;
    }

    public function sendInvitation(User $user, User $actor): void
    {
        if ($user->auth_source !== AuthSource::Local) {
            throw new InvalidArgumentException('Only local accounts are invited.');
        }

        $broker = $this->brokers->broker('invites');
        $user->notify(new PasswordLink($broker->createToken($user), invitation: true));
        $this->trail->record(AuditEvent::Invited, $user, null, ['email' => $user->email], $actor->id);
    }

    /**
     * @param  list<string>  $roleIds
     * @return list<Role>
     */
    private function assignableRoles(array $roleIds, string $organizationId): array
    {
        $roles = Role::query()
            ->whereKey($roleIds)
            ->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $organizationId))
            ->get();

        if ($roles->count() !== count(array_unique($roleIds))) {
            throw new InvalidArgumentException('Unknown role for this organization.');
        }

        return array_values($roles->all());
    }
}
