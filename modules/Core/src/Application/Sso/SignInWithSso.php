<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Sso;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Authorization\Role;
use Modules\Core\Domain\Directory\AuthSource;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Sso\SsoGroupMapping;
use Modules\Core\Domain\Sso\SsoIdentity;
use Modules\Core\Domain\Sso\SsoProvider;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * The account a person signed in at an identity provider gets (ADR 0038,
 * owner's decisions): the one already linked to them; else an existing
 * account with their email, linked only when it belongs to the provider's
 * organization alone (so the administrator of one organization cannot take
 * over accounts of another); else a new one. Accounts created this way get
 * their roles from the provider's group mappings, Requester when none match.
 */
final readonly class SignInWithSso
{
    /** Authentication methods (RFC 8176) that are factors of their own. */
    private const array FACTORS = ['pwd', 'otp', 'hwk', 'swk', 'sms', 'tel', 'fpt', 'face', 'iris', 'retina', 'vbm', 'pin', 'kba', 'sc'];

    public function __construct(
        private ConnectionInterface $db,
        private OrganizationContext $context,
        private AuditTrail $trail,
    ) {}

    /**
     * @param  array<string, mixed>  $claims  Of a verified ID token.
     *
     * @throws SsoFailed
     */
    public function handle(SsoProvider $provider, array $claims): User
    {
        try {
            return $this->resolve($provider, $claims);
        } catch (SsoFailed $failure) {
            // Recorded once the transaction is over, so the record is not rolled back with it.
            $this->trail->record(AuditEvent::LoginDenied, $failure->user, null, [
                'email' => is_string($claims['email'] ?? null) ? $claims['email'] : null,
                'provider' => $provider->name,
                'reason' => $failure->getMessage(),
            ]);

            throw $failure;
        }
    }

    /**
     * @param  array<string, mixed>  $claims
     *
     * @throws SsoFailed
     */
    private function resolve(SsoProvider $provider, array $claims): User
    {
        $email = is_string($claims['email'] ?? null) ? Str::lower(trim($claims['email'])) : (is_string($claims['preferred_username'] ?? null) ? Str::lower(trim($claims['preferred_username'])) : '');
        $subject = is_string($claims['sub'] ?? null) ? $claims['sub'] : '';

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || ($claims['email_verified'] ?? true) === false) {
            throw SsoFailed::because('email');
        }

        if (! $provider->allowsEmail($email)) {
            throw SsoFailed::because('domain');
        }

        return $this->db->transaction(function () use ($provider, $claims, $email, $subject): User {
            $identity = SsoIdentity::query()->with('user')->where('provider_id', $provider->id)->where('subject', $subject)->lockForUpdate()->first();
            $user = $identity !== null ? $identity->user : $this->accountFor($provider, $email, $claims);

            if (! $user->is_active || ! $user->canSignIn()) {
                throw SsoFailed::because('inactive', $user);
            }

            if ($identity === null) {
                $identity = new SsoIdentity;
                $identity->forceFill(['provider_id' => $provider->id, 'subject' => $subject, 'user_id' => $user->id]);
            }

            $identity->forceFill(['last_login_at' => now()])->save();

            // Accounts of the provider follow its groups; linked local or AD accounts keep their own roles.
            if ($user->auth_source === AuthSource::Sso) {
                $this->syncRoles($provider, $user, $this->groups($provider, $claims));
            }

            return $user;
        });
    }

    /**
     * Whether the provider says the person gave more than a password.
     *
     * @param  array<string, mixed>  $claims
     */
    public function mfaAsserted(array $claims): bool
    {
        $methods = array_values(array_filter(is_array($claims['amr'] ?? null) ? $claims['amr'] : [], is_string(...)));

        return in_array('mfa', $methods, true) || count(array_unique(array_intersect($methods, self::FACTORS))) >= 2;
    }

    /**
     * @param  array<string, mixed>  $claims
     *
     * @throws SsoFailed
     */
    private function accountFor(SsoProvider $provider, string $email, array $claims): User
    {
        $existing = User::query()->withTrashed()->where('email', $email)->first();

        if ($existing !== null) {
            $organizations = $existing->organizations()->pluck('core_organizations.id')->all();

            if ($existing->trashed() || $organizations !== [$provider->organization_id]) {
                throw SsoFailed::because('account_exists', $existing);
            }

            return $existing;
        }

        $name = is_string($claims['name'] ?? null) && trim($claims['name']) !== '' ? trim($claims['name']) : $email;
        $user = new User;
        $user->forceFill([
            'name' => mb_substr($name, 0, 255),
            'email' => $email,
            'auth_source' => AuthSource::Sso,
            'password' => null,
            'is_active' => true,
            'current_organization_id' => $provider->organization_id,
        ])->save();
        $user->organizations()->attach($provider->organization_id);

        return $user;
    }

    /**
     * @param  list<string>  $groups
     */
    private function syncRoles(SsoProvider $provider, User $user, array $groups): void
    {
        $roleIds = SsoGroupMapping::query()->withoutGlobalScopes()
            ->where('provider_id', $provider->id)
            ->whereIn('group', $groups === [] ? [''] : $groups)
            ->pluck('role_id')->unique()->values()->all();
        $roles = Role::query()->withoutGlobalScopes()->whereKey($roleIds)->get();

        if ($roles->isEmpty()) {
            $roles = Role::query()->withoutGlobalScopes()->whereNull('organization_id')->where('name', DefaultRole::Requester->value)->get();
        }

        if (! $user->organizations()->whereKey($provider->organization_id)->exists()) {
            $user->organizations()->attach($provider->organization_id);
        }

        $this->context->runAs($provider->organization_id, function () use ($user, $roles): void {
            $user->unsetRelation('roles');
            $current = array_values(array_filter($user->roles()->pluck('core_roles.id')->all(), is_string(...)));
            $desired = $roles->map(fn (Role $role): string => $role->id)->all();

            foreach ($roles as $role) {
                if (! in_array($role->id, $current, true)) {
                    $user->assignRole($role);
                }
            }

            foreach (array_diff($current, $desired) as $roleId) {
                $user->removeRole($roleId);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $claims
     * @return list<string>
     */
    private function groups(SsoProvider $provider, array $claims): array
    {
        $groups = $claims[$provider->groups_claim] ?? [];

        return array_values(array_filter(is_array($groups) ? $groups : [$groups], fn (mixed $group): bool => is_string($group) && $group !== ''));
    }
}
