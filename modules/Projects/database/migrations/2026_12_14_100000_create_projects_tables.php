<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * IT projects (ADR 0052): a project in four phases, its team, and the tasks
 * of each phase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects_sequences', function (Blueprint $table): void {
            $table->foreignUuid('organization_id')->primary()->constrained('core_organizations')->cascadeOnDelete();
            $table->unsignedInteger('last_value');
        });

        Schema::create('projects_projects', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            $table->string('code', 16);
            $table->string('name', 150);
            $table->text('description')->nullable();
            // general, application (a new application, CLAUDE.md §9), infrastructure.
            $table->string('kind', 16)->default('general');
            $table->string('status', 16)->default('active');
            $table->foreignUuid('sponsor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('planned_start')->nullable();
            $table->date('planned_end')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'status']);
        });
        DB::statement("ALTER TABLE projects_projects ADD CONSTRAINT projects_projects_kind_check CHECK (kind IN ('general', 'application', 'infrastructure'))");
        DB::statement("ALTER TABLE projects_projects ADD CONSTRAINT projects_projects_status_check CHECK (status IN ('active', 'on_hold', 'closed', 'cancelled'))");
        DB::statement('ALTER TABLE projects_projects ADD CONSTRAINT projects_projects_dates_check CHECK (planned_start IS NULL OR planned_end IS NULL OR planned_end >= planned_start)');

        Schema::create('projects_members', function (Blueprint $table): void {
            $table->uuid('project_id');
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 100)->nullable();

            $table->primary(['project_id', 'user_id']);
            $table->foreign('project_id')->references('id')->on('projects_projects')->cascadeOnDelete();
        });

        Schema::create('projects_phases', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('project_id');
            // initiation, planning, execution, closure.
            $table->string('key', 16);
            $table->unsignedSmallInteger('position');
            $table->string('status', 16)->default('pending');
            $table->date('planned_start')->nullable();
            $table->date('planned_end')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(['project_id', 'key']);
        });
        DB::statement('ALTER TABLE projects_phases ADD CONSTRAINT projects_phases_project_fk FOREIGN KEY (organization_id, project_id) REFERENCES projects_projects (organization_id, id) ON DELETE CASCADE');
        DB::statement("ALTER TABLE projects_phases ADD CONSTRAINT projects_phases_key_check CHECK (key IN ('initiation', 'planning', 'execution', 'closure'))");
        DB::statement("ALTER TABLE projects_phases ADD CONSTRAINT projects_phases_status_check CHECK (status IN ('pending', 'active', 'done'))");
        DB::statement('ALTER TABLE projects_phases ADD CONSTRAINT projects_phases_dates_check CHECK (planned_start IS NULL OR planned_end IS NULL OR planned_end >= planned_start)');
        // One phase at a time is under way.
        DB::statement("CREATE UNIQUE INDEX projects_phases_one_active ON projects_phases (project_id) WHERE status = 'active'");

        Schema::create('projects_tasks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('project_id');
            $table->uuid('phase_id');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->foreignUuid('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_on')->nullable();
            $table->string('status', 16)->default('todo');
            $table->unsignedInteger('position')->default(0);
            $table->timestampTz('completed_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['project_id', 'status', 'position']);
            $table->index(['assignee_id', 'status']);
        });
        DB::statement('ALTER TABLE projects_tasks ADD CONSTRAINT projects_tasks_project_fk FOREIGN KEY (organization_id, project_id) REFERENCES projects_projects (organization_id, id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE projects_tasks ADD CONSTRAINT projects_tasks_phase_fk FOREIGN KEY (organization_id, phase_id) REFERENCES projects_phases (organization_id, id) ON DELETE CASCADE');
        DB::statement("ALTER TABLE projects_tasks ADD CONSTRAINT projects_tasks_status_check CHECK (status IN ('todo', 'in_progress', 'blocked', 'done'))");
        DB::statement("ALTER TABLE projects_tasks ADD CONSTRAINT projects_tasks_done_check CHECK ((status = 'done') = (completed_at IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('projects_tasks');
        Schema::dropIfExists('projects_phases');
        Schema::dropIfExists('projects_members');
        Schema::dropIfExists('projects_projects');
        Schema::dropIfExists('projects_sequences');
    }
};
