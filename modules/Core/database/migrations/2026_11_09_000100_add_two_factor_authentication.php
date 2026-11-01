<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-factor authentication with an authenticator app (TOTP, RFC 6238) and
 * account self-service (ADR 0017).
 */
return new class extends Migration
{
    private const string EVENTS = "'created', 'updated', 'deleted', 'restored', 'force_deleted',
        'role_assigned', 'role_revoked', 'permission_granted', 'permission_revoked',
        'login', 'login_failed', 'logout', 'lockout', 'login_denied',
        'exported', 'export_downloaded'";

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Encrypted by the model; set only once the user confirmed a first code.
            $table->text('two_factor_secret')->nullable();
            $table->timestampTz('two_factor_confirmed_at')->nullable();
            // SHA-256 hashes of the unused recovery codes.
            $table->jsonb('two_factor_recovery_codes')->nullable();
            // Time step of the last accepted code: a code is never accepted twice.
            $table->bigInteger('two_factor_last_used_step')->nullable();
        });

        DB::statement('ALTER TABLE users ADD CONSTRAINT users_two_factor_complete CHECK ((two_factor_confirmed_at IS NULL) = (two_factor_secret IS NULL))');

        DB::statement('ALTER TABLE core_audit_logs DROP CONSTRAINT core_audit_logs_event_check');
        DB::statement('ALTER TABLE core_audit_logs ADD CONSTRAINT core_audit_logs_event_check CHECK (event IN ('.self::EVENTS.",
            'mfa_enabled', 'mfa_disabled', 'mfa_reset', 'mfa_failed', 'mfa_recovery_code_used', 'mfa_recovery_codes_regenerated',
            'password_changed', 'password_reset', 'invited'))");
    }

    public function down(): void
    {
        // Audit entries are append-only: keep the old check valid for new rows only.
        DB::statement('ALTER TABLE core_audit_logs DROP CONSTRAINT core_audit_logs_event_check');
        DB::statement('ALTER TABLE core_audit_logs ADD CONSTRAINT core_audit_logs_event_check CHECK (event IN ('.self::EVENTS.')) NOT VALID');

        DB::statement('ALTER TABLE users DROP CONSTRAINT users_two_factor_complete');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['two_factor_secret', 'two_factor_confirmed_at', 'two_factor_recovery_codes', 'two_factor_last_used_step']);
        });
    }
};
