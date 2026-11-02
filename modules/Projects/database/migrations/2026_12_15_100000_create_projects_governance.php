<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stage gates and status reports (ADR 0053): moving to the next phase - or
 * closing the project - waits for the sponsor; the project manager reports
 * green, amber or red at regular intervals.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects_gate_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('project_id');
            $table->uuid('from_phase_id');
            // Null when the request is to close the project after its closure phase.
            $table->uuid('to_phase_id')->nullable();
            $table->text('note')->nullable();
            $table->string('status', 16)->default('pending');
            $table->foreignUuid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestampsTz();

            $table->index(['project_id', 'created_at']);
        });
        DB::statement('ALTER TABLE projects_gate_requests ADD CONSTRAINT projects_gate_requests_project_fk FOREIGN KEY (organization_id, project_id) REFERENCES projects_projects (organization_id, id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE projects_gate_requests ADD CONSTRAINT projects_gate_requests_from_fk FOREIGN KEY (organization_id, from_phase_id) REFERENCES projects_phases (organization_id, id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE projects_gate_requests ADD CONSTRAINT projects_gate_requests_to_fk FOREIGN KEY (organization_id, to_phase_id) REFERENCES projects_phases (organization_id, id) ON DELETE CASCADE');
        DB::statement("ALTER TABLE projects_gate_requests ADD CONSTRAINT projects_gate_requests_status_check CHECK (status IN ('pending', 'approved', 'rejected'))");
        DB::statement("ALTER TABLE projects_gate_requests ADD CONSTRAINT projects_gate_requests_decided_check CHECK ((status = 'pending') = (decided_at IS NULL))");
        // The sponsor decides on one request at a time.
        DB::statement("CREATE UNIQUE INDEX projects_gate_requests_one_pending ON projects_gate_requests (project_id) WHERE status = 'pending'");

        Schema::create('projects_status_reports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('project_id');
            $table->date('reported_on');
            $table->string('overall', 8);
            $table->string('schedule', 8);
            $table->string('budget', 8);
            $table->string('scope', 8);
            $table->text('achievements');
            $table->text('next_steps');
            $table->text('issues')->nullable();
            $table->foreignUuid('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['project_id', 'reported_on']);
        });
        DB::statement('ALTER TABLE projects_status_reports ADD CONSTRAINT projects_status_reports_project_fk FOREIGN KEY (organization_id, project_id) REFERENCES projects_projects (organization_id, id) ON DELETE CASCADE');
        foreach (['overall', 'schedule', 'budget', 'scope'] as $column) {
            DB::statement("ALTER TABLE projects_status_reports ADD CONSTRAINT projects_status_reports_{$column}_check CHECK ({$column} IN ('green', 'amber', 'red'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('projects_status_reports');
        Schema::dropIfExists('projects_gate_requests');
    }
};
