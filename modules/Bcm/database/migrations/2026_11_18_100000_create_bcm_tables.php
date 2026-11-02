<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Continuity and recovery plans (ADR 0026): business continuity plans (BCP) and
 * IT disaster recovery plans (DRP), each in approved versions with their scope,
 * crisis team and runbook. Business processes and configuration items belong to
 * other modules and are referenced by id through Core contracts, without
 * foreign keys.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bcm_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            $table->string('type', 8);
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('deputy_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('active');
            // Copied from the approved version, for lists and reminders.
            $table->unsignedInteger('approved_version')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->date('next_review_on')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['organization_id', 'id']);
            $table->index('owner_id');
            $table->index('deputy_id');
        });
        DB::statement(<<<'SQL'
            ALTER TABLE bcm_plans
                ADD CONSTRAINT bcm_plans_type_check CHECK (type IN ('bcp', 'drp')),
                ADD CONSTRAINT bcm_plans_status_check CHECK (status IN ('active', 'retired'))
            SQL);
        DB::statement('CREATE UNIQUE INDEX bcm_plans_name_unique ON bcm_plans (organization_id, lower(name)) WHERE deleted_at IS NULL');

        Schema::create('bcm_plan_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->cascadeOnDelete();
            $table->uuid('plan_id');
            $table->unsignedInteger('version');
            $table->string('status', 16)->default('draft');
            $table->text('objective')->nullable();
            // Scenarios the plan covers, from a fixed list.
            $table->jsonb('scenarios')->default('[]');
            $table->text('activation_criteria')->nullable();
            // Who may declare the crisis and activate the plan.
            $table->text('activation_authority')->nullable();
            $table->text('communication')->nullable();
            $table->text('strategy')->nullable();
            $table->text('assumptions')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->foreignUuid('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('decided_at')->nullable();
            $table->foreignUuid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_comment')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(['plan_id', 'version']);
        });
        DB::statement(<<<'SQL'
            ALTER TABLE bcm_plan_versions
                ADD CONSTRAINT bcm_plan_versions_status_check CHECK (status IN ('draft', 'submitted', 'returned', 'approved', 'superseded')),
                ADD CONSTRAINT bcm_plan_versions_scenarios_check CHECK (jsonb_typeof(scenarios) = 'array'),
                ADD CONSTRAINT bcm_plan_versions_decided_check CHECK ((status IN ('approved', 'superseded', 'returned')) = (decided_at IS NOT NULL)),
                ADD CONSTRAINT bcm_plan_versions_plan_fk FOREIGN KEY (organization_id, plan_id)
                    REFERENCES bcm_plans (organization_id, id) ON DELETE CASCADE
            SQL);
        DB::statement("CREATE UNIQUE INDEX bcm_plan_versions_open_unique ON bcm_plan_versions (plan_id) WHERE status IN ('draft', 'submitted', 'returned')");
        DB::statement("CREATE UNIQUE INDEX bcm_plan_versions_current_unique ON bcm_plan_versions (plan_id) WHERE status = 'approved'");

        // Processes of the BIA the version covers (by id, through Core's BusinessProcesses contract).
        Schema::create('bcm_version_processes', function (Blueprint $table): void {
            $table->uuid('version_id');
            $table->uuid('organization_id');
            $table->uuid('process_id');

            $table->primary(['version_id', 'process_id']);
            $table->index('process_id');
        });
        DB::statement('ALTER TABLE bcm_version_processes ADD CONSTRAINT bcm_version_processes_version_fk FOREIGN KEY (organization_id, version_id) REFERENCES bcm_plan_versions (organization_id, id) ON DELETE CASCADE');

        // The crisis team: a role filled by a person of the organization or an outside contact.
        Schema::create('bcm_team_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('version_id');
            $table->unsignedInteger('position');
            $table->string('role');
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 64)->nullable();
            $table->string('contact_email')->nullable();
            $table->boolean('is_deputy')->default(false);
            $table->string('responsibilities', 2000)->nullable();

            $table->index('version_id');
        });
        DB::statement(<<<'SQL'
            ALTER TABLE bcm_team_members
                ADD CONSTRAINT bcm_team_members_who_check CHECK (user_id IS NOT NULL OR contact_name IS NOT NULL),
                ADD CONSTRAINT bcm_team_members_version_fk FOREIGN KEY (organization_id, version_id)
                    REFERENCES bcm_plan_versions (organization_id, id) ON DELETE CASCADE
            SQL);

        // The runbook: ordered steps, by phase.
        Schema::create('bcm_steps', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('version_id');
            $table->unsignedInteger('position');
            $table->string('phase', 16);
            $table->text('action');
            $table->string('responsible')->nullable();
            $table->foreignUuid('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('estimated_minutes')->nullable();
            // A configuration item the step works on (through Core's ConfigurationItems contract).
            $table->uuid('configuration_item_id')->nullable();
            $table->string('notes', 2000)->nullable();

            $table->index('version_id');
        });
        DB::statement(<<<'SQL'
            ALTER TABLE bcm_steps
                ADD CONSTRAINT bcm_steps_phase_check CHECK (phase IN ('activation', 'recovery', 'resumption', 'restoration')),
                ADD CONSTRAINT bcm_steps_version_fk FOREIGN KEY (organization_id, version_id)
                    REFERENCES bcm_plan_versions (organization_id, id) ON DELETE CASCADE
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('bcm_steps');
        Schema::dropIfExists('bcm_team_members');
        Schema::dropIfExists('bcm_version_processes');
        Schema::dropIfExists('bcm_plan_versions');
        Schema::dropIfExists('bcm_plans');
    }
};
