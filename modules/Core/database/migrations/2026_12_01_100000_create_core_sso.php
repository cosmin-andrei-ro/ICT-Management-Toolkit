<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Single sign-on through OpenID Connect (ADR 0038): identity providers of an
 * organization, their group to role mappings, and the accounts they sign in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_sso_providers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('issuer', 500);
            $table->string('client_id', 255);
            // Encrypted with the application key; never shown again.
            $table->text('client_secret');
            $table->string('scopes', 255)->default('openid email profile');
            $table->string('groups_claim', 100)->default('groups');
            // Only accounts of these email domains sign in through this provider.
            $table->jsonb('allowed_domains');
            // Skip the toolkit's own second factor when the provider says it asked for one (amr).
            $table->boolean('trust_mfa')->default(true);
            $table->boolean('is_enabled')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
        });

        Schema::create('core_sso_group_mappings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('provider_id');
            // The group as the provider sends it (a group id or path…).
            $table->string('group', 500);
            $table->foreignUuid('role_id')->constrained('core_roles')->cascadeOnDelete();
            $table->timestampsTz();

            $table->unique(['provider_id', 'group', 'role_id']);
        });
        DB::statement('ALTER TABLE core_sso_group_mappings ADD CONSTRAINT core_sso_group_mappings_provider_fk FOREIGN KEY (organization_id, provider_id) REFERENCES core_sso_providers (organization_id, id) ON DELETE CASCADE');

        Schema::create('core_sso_identities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('provider_id')->constrained('core_sso_providers')->cascadeOnDelete();
            // The provider's stable identifier of the person ("sub").
            $table->string('subject', 255);
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('last_login_at')->nullable();
            $table->timestampsTz();

            $table->unique(['provider_id', 'subject']);
            $table->index('user_id');
        });

        // Accounts created by single sign-on have no password of their own.
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_auth_source_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_auth_source_check CHECK (auth_source IN ('local', 'ldap', 'sso'))");
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_credentials_match_source');
        DB::statement(<<<'SQL'
            ALTER TABLE users ADD CONSTRAINT users_credentials_match_source CHECK (
                (auth_source = 'local' AND password IS NOT NULL AND directory_guid IS NULL)
                OR (auth_source = 'ldap' AND password IS NULL AND directory_guid IS NOT NULL)
                OR (auth_source = 'sso' AND password IS NULL AND directory_guid IS NULL)
            )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('core_sso_identities');
        Schema::dropIfExists('core_sso_group_mappings');
        Schema::dropIfExists('core_sso_providers');

        DB::statement('ALTER TABLE users DROP CONSTRAINT users_credentials_match_source');
        DB::statement(<<<'SQL'
            ALTER TABLE users ADD CONSTRAINT users_credentials_match_source CHECK (
                (auth_source = 'local' AND password IS NOT NULL AND directory_guid IS NULL)
                OR (auth_source = 'ldap' AND password IS NULL AND directory_guid IS NOT NULL)
            ) NOT VALID
            SQL);
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_auth_source_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_auth_source_check CHECK (auth_source IN ('local', 'ldap')) NOT VALID");
    }
};
