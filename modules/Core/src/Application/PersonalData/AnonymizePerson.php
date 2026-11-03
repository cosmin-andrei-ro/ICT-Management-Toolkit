<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\PersonalData;

use Illuminate\Cache\RateLimiter;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Core\Application\Authentication\TwoFactorAuthentication;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\PersonalData\PersonalData;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use SensitiveParameter;

/**
 * Anonymizes a person on request (GDPR Art. 17, ADR 0059). The account stays,
 * so the records the person is part of keep their history, but it loses the
 * name, the e-mail address and every way to sign in; the modules erase what
 * is only personal; the audit log keeps who did what, without the name, the
 * address, the IP addresses and browsers. Irreversible: it takes a fresh
 * authenticator code from the administrator and the person's e-mail typed out.
 */
final readonly class AnonymizePerson
{
    private const int ATTEMPTS_PER_MINUTE = 5;

    public function __construct(
        private ConnectionInterface $db,
        private OrganizationContext $context,
        private TwoFactorAuthentication $twoFactor,
        private PersonalData $personalData,
        private AuditTrail $trail,
        private RateLimiter $limiter,
    ) {}

    /**
     * @return int Audit entries redacted.
     *
     * @throws ValidationException keyed to "person", "confirmation" or "code".
     */
    public function handle(User $person, string $confirmation, #[SensitiveParameter] string $code, User $administrator): int
    {
        $organizationId = $this->context->requireId();
        $this->ensureAllowed($person, $confirmation, $organizationId, $administrator);
        $this->ensureReauthenticated($administrator, $code);

        $email = $person->email;
        $reference = substr(str_replace('-', '', $person->id), -8);

        return $this->db->transaction(function () use ($person, $email, $reference, $organizationId, $administrator): int {
            foreach ($this->personalData->erasers() as $eraser) {
                $eraser($person, $email);
            }

            // Every way to sign in or to be reached ends.
            $this->db->table('sessions')->where('user_id', $person->id)->delete();
            $this->db->table('personal_access_tokens')->where('tokenable_type', $person->getMorphClass())->where('tokenable_id', $person->id)->delete();
            $this->db->table('core_security_keys')->where('user_id', $person->id)->delete();
            $this->db->table('core_sso_identities')->where('user_id', $person->id)->delete();
            $this->db->table('password_reset_tokens')->where('email', $email)->delete();
            $this->db->table('core_team_user')->where('user_id', $person->id)->delete();

            $person->syncRoles([]);
            $person->organizations()->detach($organizationId);

            $name = __('core::personal_data.anonymized_name', ['reference' => $reference]);
            $replacementEmail = 'former-'.Str::lower(str_replace('-', '', $person->id)).'@anonymized.invalid';
            $redacted = $this->db->selectOne('SELECT core_redact_person_in_audit_log(?, ?, ?, ?, ?) AS redacted', [$person->id, $person->name, $email, $name, $replacementEmail]);

            // Plain query: the old name and address must not reach the audit log as an "updated" entry.
            $this->db->table('users')->where('id', $person->id)->update([
                'name' => $name,
                'email' => $replacementEmail,
                'email_verified_at' => null,
                'password' => null,
                'remember_token' => null,
                'is_active' => false,
                'directory_guid' => null,
                'directory_username' => null,
                'two_factor_secret' => null,
                'two_factor_confirmed_at' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_last_used_step' => null,
                'failed_logins' => 0,
                'locked_at' => null,
                'current_organization_id' => null,
                'anonymized_at' => now(),
                'updated_at' => now(),
            ]);

            $count = is_object($redacted) && is_numeric($redacted->redacted ?? null) ? (int) $redacted->redacted : 0;
            $person->refresh();
            $this->trail->record(AuditEvent::PersonAnonymized, $person, null, ['audit_entries_redacted' => $count, 'organization' => $organizationId], $administrator->id);

            return $count;
        });
    }

    private function ensureAllowed(User $person, string $confirmation, string $organizationId, User $administrator): void
    {
        $error = match (true) {
            $person->anonymized_at !== null => 'already',
            $person->id === $administrator->id => 'self',
            ! $person->organizations()->whereKey($organizationId)->exists() => 'not_member',
            // Each organization answers for its own data: the account is shared with the others.
            $person->organizations()->whereKeyNot($organizationId)->exists() => 'other_organizations',
            $person->hasRole(DefaultRole::Administrator->value) && ! User::query()->whereKeyNot($person->id)->whereNull('anonymized_at')->role(DefaultRole::Administrator->value)->exists() => 'last_administrator',
            default => null,
        };

        if ($error !== null) {
            throw ValidationException::withMessages(['person' => __('core::personal_data.anonymize.errors.'.$error)]);
        }

        if (Str::lower(trim($confirmation)) !== Str::lower($person->email)) {
            throw ValidationException::withMessages(['confirmation' => __('core::personal_data.anonymize.errors.confirmation')]);
        }
    }

    private function ensureReauthenticated(User $administrator, #[SensitiveParameter] string $code): void
    {
        $throttle = 'anonymize:'.$administrator->id;

        if ($this->limiter->tooManyAttempts($throttle, self::ATTEMPTS_PER_MINUTE)) {
            throw ValidationException::withMessages(['code' => __('auth.throttle', ['seconds' => $this->limiter->availableIn($throttle)])]);
        }

        if (! $this->twoFactor->verify($administrator, $code)) {
            $this->limiter->hit($throttle, 60);
            $this->trail->record(AuditEvent::MfaFailed, $administrator, null, ['purpose' => 'anonymize_person'], $administrator->id);

            throw ValidationException::withMessages(['code' => __('core::personal_data.anonymize.errors.code')]);
        }

        $this->limiter->clear($throttle);
    }
}
