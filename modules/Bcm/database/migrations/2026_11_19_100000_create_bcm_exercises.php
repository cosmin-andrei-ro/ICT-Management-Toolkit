<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Exercises and recovery tests of approved plans (ADR 0027): the runbook as it
 * was when the exercise started, the result of each step, which configuration
 * items were brought back and in how long, and the findings, each of which can
 * become a problem in the service desk (referenced by id, without a foreign key).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bcm_plans', function (Blueprint $table): void {
            $table->date('last_exercised_on')->nullable();
        });

        Schema::create('bcm_exercises', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->cascadeOnDelete();
            $table->uuid('plan_id');
            // The approved version exercised, set when the exercise starts.
            $table->uuid('version_id')->nullable();
            $table->string('type', 16);
            $table->string('scenario', 32)->nullable();
            $table->string('title');
            $table->date('scheduled_on');
            $table->string('status', 16)->default('planned');
            $table->foreignUuid('facilitator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('participants')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->string('outcome', 16)->nullable();
            // Activation and recovery as measured, against the strictest RTO when the exercise ended.
            $table->unsignedInteger('measured_minutes')->nullable();
            $table->unsignedInteger('rto_minutes')->nullable();
            $table->text('summary')->nullable();
            $table->text('lessons')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->index(['plan_id', 'scheduled_on']);
        });
        DB::statement(<<<'SQL'
            ALTER TABLE bcm_exercises
                ADD CONSTRAINT bcm_exercises_type_check CHECK (type IN ('tabletop', 'walkthrough', 'technical', 'full')),
                ADD CONSTRAINT bcm_exercises_scenario_check CHECK (scenario IS NULL OR scenario IN ('site_loss', 'it_outage', 'cyber_attack', 'supplier_failure', 'staff_unavailable', 'utility_failure')),
                ADD CONSTRAINT bcm_exercises_status_check CHECK (status IN ('planned', 'in_progress', 'completed', 'cancelled')),
                ADD CONSTRAINT bcm_exercises_outcome_check CHECK (outcome IS NULL OR outcome IN ('successful', 'partial', 'failed')),
                ADD CONSTRAINT bcm_exercises_completed_check CHECK ((status = 'completed') = (outcome IS NOT NULL AND completed_at IS NOT NULL)),
                ADD CONSTRAINT bcm_exercises_started_check CHECK (status IN ('planned', 'cancelled') OR (version_id IS NOT NULL AND started_at IS NOT NULL)),
                ADD CONSTRAINT bcm_exercises_plan_fk FOREIGN KEY (organization_id, plan_id)
                    REFERENCES bcm_plans (organization_id, id) ON DELETE CASCADE,
                ADD CONSTRAINT bcm_exercises_version_fk FOREIGN KEY (organization_id, version_id)
                    REFERENCES bcm_plan_versions (organization_id, id) ON DELETE CASCADE
            SQL);

        // The runbook as exercised: copied from the version, so later versions do not rewrite the record.
        Schema::create('bcm_exercise_steps', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('exercise_id');
            $table->unsignedInteger('position');
            $table->string('phase', 16);
            $table->text('action');
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->uuid('configuration_item_id')->nullable();
            $table->string('result', 16)->default('pending');
            $table->unsignedInteger('actual_minutes')->nullable();
            $table->string('notes', 2000)->nullable();

            $table->unique(['organization_id', 'id']);
            $table->index('exercise_id');
        });
        DB::statement(<<<'SQL'
            ALTER TABLE bcm_exercise_steps
                ADD CONSTRAINT bcm_exercise_steps_phase_check CHECK (phase IN ('activation', 'recovery', 'resumption', 'restoration')),
                ADD CONSTRAINT bcm_exercise_steps_result_check CHECK (result IN ('pending', 'passed', 'issues', 'failed', 'skipped')),
                ADD CONSTRAINT bcm_exercise_steps_exercise_fk FOREIGN KEY (organization_id, exercise_id)
                    REFERENCES bcm_exercises (organization_id, id) ON DELETE CASCADE
            SQL);

        // Configuration items the exercise brought back (through Core's ConfigurationItems contract).
        Schema::create('bcm_exercise_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('exercise_id');
            $table->uuid('configuration_item_id');
            $table->boolean('recovered')->nullable();
            $table->unsignedInteger('measured_minutes')->nullable();
            $table->string('notes', 2000)->nullable();

            $table->unique(['exercise_id', 'configuration_item_id']);
        });
        DB::statement('ALTER TABLE bcm_exercise_items ADD CONSTRAINT bcm_exercise_items_exercise_fk FOREIGN KEY (organization_id, exercise_id) REFERENCES bcm_exercises (organization_id, id) ON DELETE CASCADE');

        Schema::create('bcm_exercise_findings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('exercise_id');
            $table->uuid('exercise_step_id')->nullable();
            $table->text('description');
            $table->string('severity', 16);
            // The problem raised in the service desk (through Core's ServiceDeskTickets contract).
            $table->uuid('ticket_id')->nullable();
            $table->string('ticket_number', 32)->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index('exercise_id');
        });
        DB::statement(<<<'SQL'
            ALTER TABLE bcm_exercise_findings
                ADD CONSTRAINT bcm_exercise_findings_severity_check CHECK (severity IN ('low', 'medium', 'high')),
                ADD CONSTRAINT bcm_exercise_findings_exercise_fk FOREIGN KEY (organization_id, exercise_id)
                    REFERENCES bcm_exercises (organization_id, id) ON DELETE CASCADE,
                ADD CONSTRAINT bcm_exercise_findings_step_fk FOREIGN KEY (organization_id, exercise_step_id)
                    REFERENCES bcm_exercise_steps (organization_id, id) ON DELETE CASCADE
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('bcm_exercise_findings');
        Schema::dropIfExists('bcm_exercise_items');
        Schema::dropIfExists('bcm_exercise_steps');
        Schema::dropIfExists('bcm_exercises');

        Schema::table('bcm_plans', function (Blueprint $table): void {
            $table->dropColumn('last_exercised_on');
        });
    }
};
