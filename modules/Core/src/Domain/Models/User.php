<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Modules\Core\Application\Notifications\PasswordLink;
use Modules\Core\Application\Policies\UserPolicy;
use Modules\Core\Database\Factories\UserFactory;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Directory\AuthSource;
use SensitiveParameter;
use Spatie\Permission\Traits\HasRoles;

/**
 * Users are global to the instance; access to business data comes from
 * organization membership, never from the user row itself.
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property ?Carbon $email_verified_at
 * @property ?string $password
 * @property AuthSource $auth_source
 * @property ?string $directory_guid
 * @property ?string $directory_username
 * @property ?Carbon $directory_synced_at
 * @property ?Carbon $directory_disabled_at
 * @property bool $is_active
 * @property ?string $current_organization_id
 * @property ?Carbon $last_login_at
 * @property ?string $remember_token
 * @property ?string $two_factor_secret
 * @property ?Carbon $two_factor_confirmed_at
 * @property ?list<string> $two_factor_recovery_codes
 * @property ?int $two_factor_last_used_step
 * @property int $failed_logins Consecutive failed sign-ins (AccountLockout).
 * @property ?Carbon $locked_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 */
#[UseFactory(UserFactory::class)]
#[UsePolicy(UserPolicy::class)]
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
final class User extends Authenticatable implements FilamentUser
{
    use Auditable;
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use HasUuids;
    use Notifiable;
    use SoftDeletes;

    /**
     * Mirrors the column default, so new instances know their source before saving.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'auth_source' => 'local',
    ];

    /**
     * @return BelongsToMany<Organization, $this>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'core_organization_user')->withTimestamps();
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function currentOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'current_organization_id');
    }

    /**
     * Requires the organization context, which the panel's "organization"
     * middleware sets before Filament's authentication check runs.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->canSignIn() && $this->can('core.admin.access');
    }

    /**
     * Deactivated locally, or disabled/removed in AD as seen by the last directory sync.
     */
    public function canSignIn(): bool
    {
        return $this->is_active && $this->directory_disabled_at === null;
    }

    public function isDirectoryAccount(): bool
    {
        return $this->auth_source === AuthSource::Ldap;
    }

    public function isMemberOf(Organization|string $organization): bool
    {
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;

        return $this->organizations()->whereKey($organizationId)->exists();
    }

    /**
     * Queued and only for local accounts (the caller filters): AD passwords are
     * changed in Windows.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification(#[SensitiveParameter] $token): void
    {
        $this->notify(new PasswordLink($token));
    }

    /**
     * Logins are recorded as security events, not as user updates.
     *
     * @return list<string>
     */
    public function auditExclude(): array
    {
        // Two-factor changes have their own audit events (mfa_*).
        // Locks and unlocks too (account_locked, account_unlocked).
        return ['last_login_at', 'directory_synced_at', 'two_factor_confirmed_at', 'two_factor_last_used_step', 'failed_logins', 'locked_at'];
    }

    /**
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => Str::lower(trim($value)));
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'auth_source' => AuthSource::class,
            'directory_synced_at' => 'datetime',
            'directory_disabled_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_recovery_codes' => 'array',
            'two_factor_last_used_step' => 'integer',
            'failed_logins' => 'integer',
            'locked_at' => 'datetime',
        ];
    }
}
