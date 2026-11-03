<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Audit;

/**
 * Mirrored by the core_audit_logs_event_check constraint: adding a case needs a migration.
 */
enum AuditEvent: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Restored = 'restored';
    case ForceDeleted = 'force_deleted';
    case RoleAssigned = 'role_assigned';
    case RoleRevoked = 'role_revoked';
    case PermissionGranted = 'permission_granted';
    case PermissionRevoked = 'permission_revoked';
    case Login = 'login';
    case LoginFailed = 'login_failed';
    case Logout = 'logout';
    case Lockout = 'lockout';
    case LoginDenied = 'login_denied';
    case Exported = 'exported';
    case ExportDownloaded = 'export_downloaded';
    case MfaEnabled = 'mfa_enabled';
    case MfaDisabled = 'mfa_disabled';
    case MfaReset = 'mfa_reset';
    case MfaFailed = 'mfa_failed';
    case MfaRecoveryCodeUsed = 'mfa_recovery_code_used';
    case MfaRecoveryCodesRegenerated = 'mfa_recovery_codes_regenerated';
    case PasswordChanged = 'password_changed';
    case PasswordReset = 'password_reset';
    case Invited = 'invited';
    case SecretRevealed = 'secret_revealed';
    case SecurityKeyAdded = 'security_key_added';
    case SecurityKeyRemoved = 'security_key_removed';
    case AccountLocked = 'account_locked';
    case AccountUnlocked = 'account_unlocked';
    case PersonAnonymized = 'person_anonymized';

    public function label(): string
    {
        return __('core::audit.events.'.$this->value);
    }

    /**
     * Sign-ins, rights, second factors, secrets and exports - the security log
     * - as opposed to the changes made to records.
     */
    public function isSecurity(): bool
    {
        return ! in_array($this, [self::Created, self::Updated, self::Deleted, self::Restored, self::ForceDeleted], true);
    }

    /**
     * @return list<self>
     */
    public static function security(): array
    {
        return array_values(array_filter(self::cases(), fn (self $event): bool => $event->isSecurity()));
    }
}
