<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Users;

use Illuminate\Contracts\Auth\PasswordBrokerFactory;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Core\Application\Notifications\OrganizationInvitationReceived;
use Modules\Core\Application\Notifications\PasswordLink;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Authorization\Role;
use Modules\Core\Domain\Directory\AuthSource;
use Modules\Core\Domain\Models\OrganizationInvitation;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Adds a person to the current organization. A new local account gets an
 * invitation link to choose its own password: administrators never know it.
 * Someone who already has an account (another organization, or AD) is asked
 * by e-mail and joins only once they accept (AcceptOrganizationInvitation):
 * the account is theirs, and other organizations rely on it too. The answer
 * is the same either way, so inviting does not reveal who has an account.
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
     */
    public function handle(string $name, string $email, array $roleIds, User $actor): void
    {
        $organizationId = $this->context->requireId();
        $email = mb_strtolower(trim($email));
        $roles = $this->assignableRoles($roleIds, $organizationId);

        [$user, $isNew, $invitation] = $this->db->transaction(function () use ($name, $email, $roles, $organizationId, $actor): array {
            $user = User::query()->where('email', $email)->first();
            $isNew = $user === null;

            if ($user !== null && ! $user->isMemberOf($organizationId)) {
                $invitation = OrganizationInvitation::query()->where('organization_id', $organizationId)->where('user_id', $user->id)->first() ?? new OrganizationInvitation;
                $invitation->forceFill([
                    'organization_id' => $organizationId,
                    'user_id' => $user->id,
                    'role_ids' => array_map(fn (Role $role): string => $role->id, $roles),
                    'invited_by' => $actor->id,
                    'expires_at' => now()->addDays(OrganizationInvitation::DAYS_VALID),
                ])->save();

                return [$user, false, $invitation];
            }

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

            return [$user, $isNew, null];
        });

        if ($isNew) {
            $this->sendInvitation($user, $actor);
        }

        if ($invitation !== null) {
            $user->notify(new OrganizationInvitationReceived($invitation->organization->name));
            $this->trail->record(AuditEvent::Invited, $user, null, ['email' => $user->email, 'existing_account' => true], $actor->id);
        }
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
