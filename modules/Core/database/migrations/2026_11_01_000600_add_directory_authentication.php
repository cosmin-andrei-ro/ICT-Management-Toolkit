<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Active Directory sign-in (ADR 0007): each account has a fixed authentication
 * source, and AD group membership maps to organizations and roles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('auth_source', 16)->default('local');
            $table->uuid('directory_guid')->nullable()->unique();
            $table->string('directory_username')->nullable();
            $table->timestampTz('directory_synced_at')->nullable();
            $table->string('password')->nullable()->change();
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_auth_source_check CHECK (auth_source IN ('local', 'ldap'))");
        // A local account always has a password; a directory account never has one,
        // so it can never be signed in to without the directory.
        DB::statement(<<<'SQL'
            ALTER TABLE users ADD CONSTRAINT users_credentials_match_source CHECK (
                (auth_source = 'local' AND password IS NOT NULL AND directory_guid IS NULL)
                OR (auth_source = 'ldap' AND password IS NULL AND directory_guid IS NOT NULL)
            )
            SQL);

        Schema::create('core_directory_group_mappings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->cascadeOnDelete();
            $table->string('group_dn', 1024);
            $table->foreignUuid('role_id')->constrained('core_roles')->cascadeOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index('organization_id');
        });

        // DNs are compared case-insensitively, as AD does; stored lowercased.
        DB::statement('ALTER TABLE core_directory_group_mappings ADD CONSTRAINT core_directory_group_mappings_dn_lowercase CHECK (group_dn = lower(group_dn))');
        DB::statement('CREATE UNIQUE INDEX core_directory_group_mappings_unique ON core_directory_group_mappings (organization_id, group_dn, role_id)');

        DB::statement('ALTER TABLE core_audit_logs DROP CONSTRAINT core_audit_logs_event_check');
        DB::statement(<<<'SQL'
            ALTER TABLE core_audit_logs ADD CONSTRAINT core_audit_logs_event_check
                CHECK (event IN (
                    'created', 'updated', 'deleted', 'restored', 'force_deleted',
                    'role_assigned', 'role_revoked', 'permission_granted', 'permission_revoked',
                    'login', 'login_failed', 'logout', 'lockout', 'login_denied'
                ))
            SQL);
    }

    /**
     * Not reversible once directory accounts exist (they have no password); fails
     * loudly in that case instead of leaving accounts that cannot sign in.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE core_audit_logs DROP CONSTRAINT core_audit_logs_event_check');
        DB::statement(<<<'SQL'
            ALTER TABLE core_audit_logs ADD CONSTRAINT core_audit_logs_event_check
                CHECK (event IN (
                    'created', 'updated', 'deleted', 'restored', 'force_deleted',
                    'role_assigned', 'role_revoked', 'permission_granted', 'permission_revoked',
                    'login', 'login_failed', 'logout', 'lockout'
                )) NOT VALID
            SQL);

        Schema::dropIfExists('core_directory_group_mappings');

        DB::statement('ALTER TABLE users DROP CONSTRAINT users_credentials_match_source');
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_auth_source_check');

        Schema::table('users', function (Blueprint $table): void {
            $table->string('password')->nullable(false)->change();
            $table->dropColumn(['auth_source', 'directory_guid', 'directory_username', 'directory_synced_at']);
        });
    }
};
