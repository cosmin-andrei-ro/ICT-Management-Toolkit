<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Exports (ADR 0016): files generated in the queue for the person who asked,
 * downloadable by them only, until they expire. Requests and downloads are audited.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_exports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            // Registered export type, e.g. "ticketing.tickets".
            $table->string('type', 64);
            $table->string('format', 8);
            $table->jsonb('parameters');
            $table->string('status', 16)->default('pending');
            $table->string('disk', 64)->nullable();
            $table->string('path')->nullable();
            $table->string('filename')->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'created_at']);
            $table->index('expires_at');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE core_exports
                ADD CONSTRAINT core_exports_format_check CHECK (format IN ('csv', 'xlsx')),
                ADD CONSTRAINT core_exports_status_check CHECK (status IN ('pending', 'ready', 'failed')),
                ADD CONSTRAINT core_exports_parameters_object CHECK (jsonb_typeof(parameters) = 'object'),
                ADD CONSTRAINT core_exports_ready_has_file CHECK (status <> 'ready' OR (path IS NOT NULL AND disk IS NOT NULL AND filename IS NOT NULL AND expires_at IS NOT NULL))
            SQL);

        DB::statement('ALTER TABLE core_audit_logs DROP CONSTRAINT core_audit_logs_event_check');
        DB::statement(<<<'SQL'
            ALTER TABLE core_audit_logs ADD CONSTRAINT core_audit_logs_event_check
                CHECK (event IN (
                    'created', 'updated', 'deleted', 'restored', 'force_deleted',
                    'role_assigned', 'role_revoked', 'permission_granted', 'permission_revoked',
                    'login', 'login_failed', 'logout', 'lockout', 'login_denied',
                    'exported', 'export_downloaded'
                ))
            SQL);
    }

    public function down(): void
    {
        // Audit entries are append-only: keep the old check valid for new rows only.
        DB::statement('ALTER TABLE core_audit_logs DROP CONSTRAINT core_audit_logs_event_check');
        DB::statement(<<<'SQL'
            ALTER TABLE core_audit_logs ADD CONSTRAINT core_audit_logs_event_check
                CHECK (event IN (
                    'created', 'updated', 'deleted', 'restored', 'force_deleted',
                    'role_assigned', 'role_revoked', 'permission_granted', 'permission_revoked',
                    'login', 'login_failed', 'logout', 'lockout', 'login_denied'
                )) NOT VALID
            SQL);

        Schema::dropIfExists('core_exports');
    }
};
