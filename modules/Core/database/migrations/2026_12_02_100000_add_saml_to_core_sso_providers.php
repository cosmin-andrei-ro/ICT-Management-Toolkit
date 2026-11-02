<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SAML 2.0 identity providers next to OpenID Connect ones (ADR 0039): for a
 * SAML provider "issuer" is the IdP entity ID, with its single sign-on URL
 * and signing certificate instead of a client.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core_sso_providers', function (Blueprint $table): void {
            $table->string('protocol', 8)->default('oidc');
            $table->string('sso_url', 500)->nullable();
            // The IdP's public signing certificate (PEM): not a secret.
            $table->text('certificate')->nullable();
            $table->string('email_attribute', 255)->nullable();
            $table->string('name_attribute', 255)->nullable();
            $table->string('client_id', 255)->nullable()->change();
            $table->text('client_secret')->nullable()->change();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE core_sso_providers
                ADD CONSTRAINT core_sso_providers_protocol_check CHECK (
                    (protocol = 'oidc' AND client_id IS NOT NULL AND client_secret IS NOT NULL)
                    OR (protocol = 'saml' AND sso_url IS NOT NULL AND certificate IS NOT NULL)
                )
            SQL);
    }

    public function down(): void
    {
        DB::table('core_sso_providers')->where('protocol', 'saml')->delete();
        DB::statement('ALTER TABLE core_sso_providers DROP CONSTRAINT core_sso_providers_protocol_check');

        Schema::table('core_sso_providers', function (Blueprint $table): void {
            $table->dropColumn(['protocol', 'sso_url', 'certificate', 'email_attribute', 'name_attribute']);
            $table->string('client_id', 255)->nullable(false)->change();
            $table->text('client_secret')->nullable(false)->change();
        });
    }
};
