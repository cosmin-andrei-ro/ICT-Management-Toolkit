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
        Schema::table('pam_accounts', function (Blueprint $table): void {
            $table->boolean('requires_approval')->default(false);
            $table->foreignUuid('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('access_minutes')->default(60);
            $table->unsignedSmallInteger('rotation_days')->nullable();
            $table->timestampTz('rotation_reminded_at')->nullable();
            $table->unsignedSmallInteger('review_days')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('review_reminded_at')->nullable();
        });
        DB::statement('ALTER TABLE pam_accounts ADD CONSTRAINT pam_accounts_access_minutes_check CHECK (access_minutes BETWEEN 5 AND 1440)');

        Schema::create('pam_access_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            $table->foreignUuid('account_id')->constrained('pam_accounts')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 500);
            $table->string('status', 16)->default('pending');
            $table->foreignUuid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('decided_at')->nullable();
            $table->string('decision_comment', 500)->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();

            $table->index(['organization_id', 'status']);
        });
        DB::statement("ALTER TABLE pam_access_requests ADD CONSTRAINT pam_access_requests_status_check CHECK (status IN ('pending', 'approved', 'rejected'))");
        // Nobody decides on their own request; the database holds the line too.
        DB::statement('ALTER TABLE pam_access_requests ADD CONSTRAINT pam_access_requests_not_own CHECK (decided_by IS NULL OR decided_by <> user_id)');
        DB::statement("CREATE UNIQUE INDEX pam_access_requests_one_waiting ON pam_access_requests (account_id, user_id) WHERE status = 'pending'");
    }

    public function down(): void
    {
        Schema::dropIfExists('pam_access_requests');
        Schema::table('pam_accounts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('approver_id');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['requires_approval', 'access_minutes', 'rotation_days', 'rotation_reminded_at', 'review_days', 'reviewed_at', 'review_reminded_at']);
        });
        DB::statement('ALTER TABLE pam_accounts DROP CONSTRAINT IF EXISTS pam_accounts_access_minutes_check');
    }
};
