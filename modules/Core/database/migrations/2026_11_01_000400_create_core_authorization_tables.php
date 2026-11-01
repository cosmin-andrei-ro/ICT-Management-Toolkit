<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * spatie/laravel-permission tables, adapted to UUIDs and to organizations as teams
 * (ADR 0004). Names must match config/permission.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_permissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 128);
            $table->string('guard_name', 32);
            $table->timestampsTz();

            $table->unique(['name', 'guard_name']);
        });

        // organization_id NULL marks a global role, defined in code and shared by all
        // organizations; organizations may also define their own roles.
        Schema::create('core_roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->nullable()->constrained('core_organizations')->cascadeOnDelete();
            $table->string('name', 128);
            $table->string('guard_name', 32);
            $table->timestampsTz();

            $table->index('organization_id');
        });

        // A plain unique index treats NULLs as distinct, which would allow duplicate global roles.
        DB::statement('CREATE UNIQUE INDEX core_roles_name_unique ON core_roles (organization_id, name, guard_name) NULLS NOT DISTINCT');

        Schema::create('core_model_has_permissions', function (Blueprint $table): void {
            $table->foreignUuid('permission_id')->constrained('core_permissions')->cascadeOnDelete();
            $table->string('model_type');
            $table->uuid('model_id');
            $table->foreignUuid('organization_id')->constrained('core_organizations')->cascadeOnDelete();

            $table->primary(['organization_id', 'permission_id', 'model_id', 'model_type']);
            $table->index(['model_id', 'model_type']);
        });

        Schema::create('core_model_has_roles', function (Blueprint $table): void {
            $table->foreignUuid('role_id')->constrained('core_roles')->cascadeOnDelete();
            $table->string('model_type');
            $table->uuid('model_id');
            $table->foreignUuid('organization_id')->constrained('core_organizations')->cascadeOnDelete();

            $table->primary(['organization_id', 'role_id', 'model_id', 'model_type']);
            $table->index(['model_id', 'model_type']);
        });

        Schema::create('core_role_has_permissions', function (Blueprint $table): void {
            $table->foreignUuid('permission_id')->constrained('core_permissions')->cascadeOnDelete();
            $table->foreignUuid('role_id')->constrained('core_roles')->cascadeOnDelete();

            $table->primary(['permission_id', 'role_id']);
            $table->index('role_id');
        });

        // Authorization changes are security events (CLAUDE.md §6) and go to the audit log.
        DB::statement('ALTER TABLE core_audit_logs DROP CONSTRAINT core_audit_logs_event_check');
        DB::statement(<<<'SQL'
            ALTER TABLE core_audit_logs ADD CONSTRAINT core_audit_logs_event_check
                CHECK (event IN (
                    'created', 'updated', 'deleted', 'restored', 'force_deleted',
                    'role_assigned', 'role_revoked', 'permission_granted', 'permission_revoked'
                ))
            SQL);
    }

    public function down(): void
    {
        // NOT VALID: audit rows with authorization events cannot be deleted (append-only),
        // so the narrower constraint applies to new rows only.
        DB::statement('ALTER TABLE core_audit_logs DROP CONSTRAINT core_audit_logs_event_check');
        DB::statement(<<<'SQL'
            ALTER TABLE core_audit_logs ADD CONSTRAINT core_audit_logs_event_check
                CHECK (event IN ('created', 'updated', 'deleted', 'restored', 'force_deleted')) NOT VALID
            SQL);

        Schema::dropIfExists('core_role_has_permissions');
        Schema::dropIfExists('core_model_has_roles');
        Schema::dropIfExists('core_model_has_permissions');
        Schema::dropIfExists('core_roles');
        Schema::dropIfExists('core_permissions');
    }
};
