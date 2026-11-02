<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Security keys and passkeys (WebAuthn, ADR 0037): a second factor next to
 * the authenticator app, and a way to sign in without a password.
 */
return new class extends Migration
{
    private const string EVENTS = "'created', 'updated', 'deleted', 'restored', 'force_deleted',
        'role_assigned', 'role_revoked', 'permission_granted', 'permission_revoked',
        'login', 'login_failed', 'logout', 'lockout', 'login_denied',
        'exported', 'export_downloaded',
        'mfa_enabled', 'mfa_disabled', 'mfa_reset', 'mfa_failed', 'mfa_recovery_code_used', 'mfa_recovery_codes_regenerated',
        'password_changed', 'password_reset', 'invited', 'secret_revealed'";

    public function up(): void
    {
        Schema::create('core_security_keys', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 100);
            // The credential id, base64url: what the browser sends back when the key is used.
            $table->string('credential_id', 1400)->unique();
            // The credential record (public key, counter, flags) as the WebAuthn library serializes it: no secret.
            $table->jsonb('record');
            // Whether it can sign in on its own (a discoverable credential, "passkey").
            $table->boolean('is_passkey')->default(false);
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampsTz();

            $table->index('user_id');
        });

        DB::statement('ALTER TABLE core_audit_logs DROP CONSTRAINT core_audit_logs_event_check');
        DB::statement('ALTER TABLE core_audit_logs ADD CONSTRAINT core_audit_logs_event_check CHECK (event IN ('.self::EVENTS.", 'security_key_added', 'security_key_removed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('core_security_keys');
        // Audit entries are append-only: keep the old check valid for new rows only.
        DB::statement('ALTER TABLE core_audit_logs DROP CONSTRAINT core_audit_logs_event_check');
        DB::statement('ALTER TABLE core_audit_logs ADD CONSTRAINT core_audit_logs_event_check CHECK (event IN ('.self::EVENTS.')) NOT VALID');
    }
};
