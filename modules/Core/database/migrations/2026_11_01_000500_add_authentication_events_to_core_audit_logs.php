<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Authentication events are security events (CLAUDE.md §6). A failed login for an
 * unknown email has no subject, so the auditable columns become nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE core_audit_logs ALTER COLUMN auditable_type DROP NOT NULL');
        DB::statement('ALTER TABLE core_audit_logs ALTER COLUMN auditable_id DROP NOT NULL');
        DB::statement('ALTER TABLE core_audit_logs ADD CONSTRAINT core_audit_logs_auditable_pair CHECK ((auditable_type IS NULL) = (auditable_id IS NULL))');

        DB::statement('ALTER TABLE core_audit_logs DROP CONSTRAINT core_audit_logs_event_check');
        DB::statement(<<<'SQL'
            ALTER TABLE core_audit_logs ADD CONSTRAINT core_audit_logs_event_check
                CHECK (event IN (
                    'created', 'updated', 'deleted', 'restored', 'force_deleted',
                    'role_assigned', 'role_revoked', 'permission_granted', 'permission_revoked',
                    'login', 'login_failed', 'logout', 'lockout'
                ))
            SQL);
    }

    /**
     * Constraints are restored as NOT VALID: existing append-only rows cannot be removed.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE core_audit_logs DROP CONSTRAINT core_audit_logs_event_check');
        DB::statement(<<<'SQL'
            ALTER TABLE core_audit_logs ADD CONSTRAINT core_audit_logs_event_check
                CHECK (event IN (
                    'created', 'updated', 'deleted', 'restored', 'force_deleted',
                    'role_assigned', 'role_revoked', 'permission_granted', 'permission_revoked'
                )) NOT VALID
            SQL);

        DB::statement('ALTER TABLE core_audit_logs DROP CONSTRAINT core_audit_logs_auditable_pair');
        DB::statement('ALTER TABLE core_audit_logs ADD CONSTRAINT core_audit_logs_auditable_required CHECK (auditable_type IS NOT NULL AND auditable_id IS NOT NULL) NOT VALID');
    }
};
