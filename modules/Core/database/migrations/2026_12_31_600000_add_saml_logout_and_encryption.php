<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core_sso_providers', function (Blueprint $table): void {
            $table->string('slo_url', 500)->nullable();
            $table->boolean('want_encrypted_assertions')->default(false);
            // The toolkit's own key pair for this provider; the private key is encrypted by the model.
            $table->text('sp_certificate')->nullable();
            $table->text('sp_private_key')->nullable();
        });

        Schema::table('users', function (Blueprint $table): void {
            // Sessions begun up to this moment are over (a provider's single logout).
            $table->timestampTz('sessions_revoked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('sessions_revoked_at');
        });

        Schema::table('core_sso_providers', function (Blueprint $table): void {
            $table->dropColumn(['slo_url', 'want_encrypted_assertions', 'sp_certificate', 'sp_private_key']);
        });
    }
};
