<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Real activations of plans, the reminders sent for reviews and exercises, and
 * nothing else (ADR 0028). An activation keeps the version in force and the
 * strictest RTO of the moment, so the record holds even after the plan changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bcm_plans', function (Blueprint $table): void {
            // Reminders already sent, by the due date they announced (bookkeeping, not audited).
            $table->date('review_reminded_for')->nullable();
            $table->date('exercise_reminded_for')->nullable();
        });

        Schema::create('bcm_activations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->cascadeOnDelete();
            $table->uuid('plan_id');
            $table->uuid('version_id');
            $table->string('scenario', 32)->nullable();
            $table->text('reason');
            $table->timestampTz('activated_at');
            $table->foreignUuid('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('restored_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->foreignUuid('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('rto_minutes')->nullable();
            $table->text('summary')->nullable();
            $table->text('lessons')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['plan_id', 'activated_at']);
        });
        DB::statement(<<<'SQL'
            ALTER TABLE bcm_activations
                ADD CONSTRAINT bcm_activations_scenario_check CHECK (scenario IS NULL OR scenario IN ('site_loss', 'it_outage', 'cyber_attack', 'supplier_failure', 'staff_unavailable', 'utility_failure')),
                ADD CONSTRAINT bcm_activations_restored_check CHECK (restored_at IS NULL OR restored_at >= activated_at),
                ADD CONSTRAINT bcm_activations_closed_check CHECK (closed_at IS NULL OR restored_at IS NOT NULL),
                ADD CONSTRAINT bcm_activations_plan_fk FOREIGN KEY (organization_id, plan_id)
                    REFERENCES bcm_plans (organization_id, id) ON DELETE CASCADE,
                ADD CONSTRAINT bcm_activations_version_fk FOREIGN KEY (organization_id, version_id)
                    REFERENCES bcm_plan_versions (organization_id, id) ON DELETE CASCADE
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('bcm_activations');

        Schema::table('bcm_plans', function (Blueprint $table): void {
            $table->dropColumn(['review_reminded_for', 'exercise_reminded_for']);
        });
    }
};
