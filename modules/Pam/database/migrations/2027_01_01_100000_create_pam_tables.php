<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pam_accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            $table->string('name');
            $table->string('username');
            $table->string('type', 32);
            $table->text('description')->nullable();
            // In other modules, referenced by id and read through Core's contracts.
            $table->uuid('configuration_item_id')->nullable();
            $table->uuid('application_id')->nullable();
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            // Encrypted with the vault's own key, never with APP_KEY.
            $table->text('secret_ciphertext')->nullable();
            $table->timestampTz('secret_changed_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['organization_id', 'name']);
        });
        DB::statement("ALTER TABLE pam_accounts ADD CONSTRAINT pam_accounts_type_check CHECK (type IN ('administrator', 'service', 'root', 'api_key', 'ssh_key', 'other'))");

        Schema::create('pam_account_grants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            $table->foreignUuid('account_id')->constrained('pam_accounts')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['account_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pam_account_grants');
        Schema::dropIfExists('pam_accounts');
    }
};
