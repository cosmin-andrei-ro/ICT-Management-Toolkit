<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What a project produces (ADR 0054): deliverables per phase, linked to the
 * records of other modules they became, and - for a new application - the
 * application itself, whose checklist must be complete before closing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects_projects', function (Blueprint $table): void {
            // The application a "new application" project brings in, through Core's Applications (no cross-module key).
            $table->uuid('application_id')->nullable()->after('kind');
        });

        Schema::create('projects_deliverables', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('project_id');
            $table->uuid('phase_id');
            $table->string('title', 200);
            // Required deliverables must be delivered before the project closes.
            $table->boolean('required')->default(true);
            // A record of another module (LinkedRecords morph type and id), title kept for readers who may not open it.
            $table->string('linked_type', 64)->nullable();
            $table->uuid('linked_id')->nullable();
            $table->string('linked_title', 255)->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->foreignUuid('delivered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['project_id', 'phase_id']);
            $table->index(['linked_type', 'linked_id']);
        });
        DB::statement('ALTER TABLE projects_deliverables ADD CONSTRAINT projects_deliverables_project_fk FOREIGN KEY (organization_id, project_id) REFERENCES projects_projects (organization_id, id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE projects_deliverables ADD CONSTRAINT projects_deliverables_phase_fk FOREIGN KEY (organization_id, phase_id) REFERENCES projects_phases (organization_id, id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE projects_deliverables ADD CONSTRAINT projects_deliverables_link_check CHECK ((linked_type IS NULL) = (linked_id IS NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('projects_deliverables');
        Schema::table('projects_projects', function (Blueprint $table): void {
            $table->dropColumn('application_id');
        });
    }
};
