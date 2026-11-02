<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Business impact analysis (ADR 0023): business processes, the impact of their
 * interruption over time, and the recovery objectives derived from it, in
 * versioned assessments approved by a coordinator.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Per organization: when impact becomes unacceptable, and how often processes are reviewed.
        Schema::create('bia_settings', function (Blueprint $table): void {
            $table->foreignUuid('organization_id')->primary()->constrained('core_organizations')->cascadeOnDelete();
            $table->unsignedSmallInteger('unacceptable_level')->default(4);
            $table->unsignedSmallInteger('review_months')->default(12);
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE bia_settings ADD CONSTRAINT bia_settings_level_check CHECK (unacceptable_level BETWEEN 2 AND 5), ADD CONSTRAINT bia_settings_review_check CHECK (review_months BETWEEN 1 AND 60)');

        Schema::create('bia_impact_categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            // What each level 1–5 means for this category, so people score alike.
            $table->jsonb('levels')->default('[]');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['organization_id', 'id']);
        });
        DB::statement("ALTER TABLE bia_impact_categories ADD CONSTRAINT bia_impact_categories_levels_check CHECK (jsonb_typeof(levels) = 'array')");
        DB::statement('CREATE UNIQUE INDEX bia_impact_categories_name_unique ON bia_impact_categories (organization_id, lower(name)) WHERE deleted_at IS NULL');

        // Points in time at which impact is scored: 4 hours, 1 day…
        Schema::create('bia_time_horizons', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            $table->string('label', 64);
            $table->unsignedInteger('minutes');
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['organization_id', 'id']);
        });
        DB::statement('ALTER TABLE bia_time_horizons ADD CONSTRAINT bia_time_horizons_minutes_check CHECK (minutes > 0)');
        DB::statement('CREATE UNIQUE INDEX bia_time_horizons_minutes_unique ON bia_time_horizons (organization_id, minutes) WHERE deleted_at IS NULL');

        Schema::create('bia_processes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            $table->uuid('department_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('deputy_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('peak_periods')->nullable();
            $table->string('status', 16)->default('active');
            // Copied from the approved assessment in force, for lists, reports and the gap analysis.
            $table->unsignedInteger('mtpd_minutes')->nullable();
            $table->unsignedInteger('rto_minutes')->nullable();
            $table->unsignedInteger('rpo_minutes')->nullable();
            $table->unsignedSmallInteger('tier')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->date('next_review_on')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['organization_id', 'id']);
            $table->index(['organization_id', 'department_id']);
            $table->index('owner_id');
            $table->index('deputy_id');
        });
        DB::statement(<<<'SQL'
            ALTER TABLE bia_processes
                ADD CONSTRAINT bia_processes_status_check CHECK (status IN ('active', 'retired')),
                ADD CONSTRAINT bia_processes_tier_check CHECK (tier IS NULL OR tier BETWEEN 1 AND 4),
                ADD CONSTRAINT bia_processes_department_fk FOREIGN KEY (organization_id, department_id)
                    REFERENCES core_departments (organization_id, id) ON DELETE SET NULL (department_id)
            SQL);
        DB::statement('CREATE UNIQUE INDEX bia_processes_name_unique ON bia_processes (organization_id, lower(name)) WHERE deleted_at IS NULL');

        Schema::create('bia_assessments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->cascadeOnDelete();
            $table->uuid('process_id');
            $table->unsignedInteger('version');
            $table->string('status', 16)->default('draft');
            // The threshold in force when the assessment was approved, so its MTPD can be explained later.
            $table->unsignedSmallInteger('unacceptable_level')->nullable();
            $table->unsignedInteger('mtpd_minutes')->nullable();
            // Set when the MTPD differs from the one the scores give.
            $table->text('mtpd_reason')->nullable();
            $table->unsignedInteger('rto_minutes')->nullable();
            $table->unsignedInteger('rpo_minutes')->nullable();
            $table->text('mbco')->nullable();
            $table->text('workarounds')->nullable();
            $table->unsignedInteger('minimum_staff')->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->foreignUuid('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('decided_at')->nullable();
            $table->foreignUuid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_comment')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(['process_id', 'version']);
        });
        DB::statement(<<<'SQL'
            ALTER TABLE bia_assessments
                ADD CONSTRAINT bia_assessments_status_check CHECK (status IN ('draft', 'submitted', 'returned', 'approved', 'superseded')),
                ADD CONSTRAINT bia_assessments_rto_check CHECK (rto_minutes IS NULL OR mtpd_minutes IS NULL OR rto_minutes <= mtpd_minutes),
                ADD CONSTRAINT bia_assessments_decided_check CHECK ((status IN ('approved', 'superseded', 'returned')) = (decided_at IS NOT NULL)),
                ADD CONSTRAINT bia_assessments_approved_complete CHECK (status NOT IN ('approved', 'superseded') OR rto_minutes IS NOT NULL),
                ADD CONSTRAINT bia_assessments_process_fk FOREIGN KEY (organization_id, process_id)
                    REFERENCES bia_processes (organization_id, id) ON DELETE CASCADE
            SQL);
        // One assessment in progress per process; approved and superseded ones stay as history.
        DB::statement("CREATE UNIQUE INDEX bia_assessments_open_unique ON bia_assessments (process_id) WHERE status IN ('draft', 'submitted', 'returned')");
        DB::statement("CREATE UNIQUE INDEX bia_assessments_current_unique ON bia_assessments (process_id) WHERE status = 'approved'");

        Schema::create('bia_impact_scores', function (Blueprint $table): void {
            $table->uuid('assessment_id');
            $table->uuid('organization_id');
            $table->uuid('category_id');
            $table->uuid('horizon_id');
            $table->unsignedSmallInteger('score');

            $table->primary(['assessment_id', 'category_id', 'horizon_id']);
        });
        DB::statement(<<<'SQL'
            ALTER TABLE bia_impact_scores
                ADD CONSTRAINT bia_impact_scores_score_check CHECK (score BETWEEN 1 AND 5),
                ADD CONSTRAINT bia_impact_scores_assessment_fk FOREIGN KEY (organization_id, assessment_id)
                    REFERENCES bia_assessments (organization_id, id) ON DELETE CASCADE,
                ADD CONSTRAINT bia_impact_scores_category_fk FOREIGN KEY (organization_id, category_id)
                    REFERENCES bia_impact_categories (organization_id, id) ON DELETE RESTRICT,
                ADD CONSTRAINT bia_impact_scores_horizon_fk FOREIGN KEY (organization_id, horizon_id)
                    REFERENCES bia_time_horizons (organization_id, id) ON DELETE RESTRICT
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('bia_impact_scores');
        Schema::dropIfExists('bia_assessments');
        Schema::dropIfExists('bia_processes');
        Schema::dropIfExists('bia_time_horizons');
        Schema::dropIfExists('bia_impact_categories');
        Schema::dropIfExists('bia_settings');
    }
};
