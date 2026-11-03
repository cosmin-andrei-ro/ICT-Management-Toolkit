<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Account lockout after consecutive failed sign-ins (CLAUDE.md §6, ADR 0057)
 * and the organization's security settings: which roles must use two-factor
 * authentication.
 */
return new class extends Migration
{
    private const string EVENTS = "'created', 'updated', 'deleted', 'restored', 'force_deleted',
        'role_assigned', 'role_revoked', 'permission_granted', 'permission_revoked',
        'login', 'login_failed', 'logout', 'lockout', 'login_denied',
        'exported', 'export_downloaded',
        'mfa_enabled', 'mfa_disabled', 'mfa_reset', 'mfa_failed', 'mfa_recovery_code_used', 'mfa_recovery_codes_regenerated',
        'password_changed', 'password_reset', 'invited', 'secret_revealed', 'security_key_added', 'security_key_removed'";

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Consecutive failures: any successful sign-in starts again from zero.
            $table->unsignedSmallInteger('failed_logins')->default(0);
            $table->timestampTz('locked_at')->nullable();
        });

        Schema::create('core_security_settings', function (Blueprint $table): void {
            $table->foreignUuid('organization_id')->primary()->constrained('core_organizations')->cascadeOnDelete();
            // Role names whose holders must use two-factor authentication; null: every role but requester.
            $table->jsonb('mfa_required_roles')->nullable();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE core_audit_logs DROP CONSTRAINT core_audit_logs_event_check');
        DB::statement('ALTER TABLE core_audit_logs ADD CONSTRAINT core_audit_logs_event_check CHECK (event IN ('.self::EVENTS.", 'account_locked', 'account_unlocked'))");
    }

    public function down(): void
    {
        // Audit entries are append-only: keep the old check valid for new rows only.
        DB::statement('ALTER TABLE core_audit_logs DROP CONSTRAINT core_audit_logs_event_check');
        DB::statement('ALTER TABLE core_audit_logs ADD CONSTRAINT core_audit_logs_event_check CHECK (event IN ('.self::EVENTS.')) NOT VALID');

        Schema::dropIfExists('core_security_settings');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['failed_logins', 'locked_at']);
        });
    }
};
