<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What a process needs besides IT systems (ADR 0024): suppliers, locations,
 * upstream processes, key roles and key people. Configuration items are linked
 * through the CMDB's own link table. Kept per assessment version, as part of
 * what was approved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bia_dependencies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->cascadeOnDelete();
            $table->uuid('assessment_id');
            $table->string('kind', 16);
            $table->uuid('supplier_id')->nullable();
            $table->uuid('location_id')->nullable();
            $table->uuid('upstream_process_id')->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('role_name')->nullable();
            $table->unsignedInteger('minimum_people')->nullable();
            $table->string('note', 1000)->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at');

            $table->index('assessment_id');
            $table->index('supplier_id');
            $table->index('location_id');
            $table->index('upstream_process_id');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE bia_dependencies
                ADD CONSTRAINT bia_dependencies_kind_check CHECK (kind IN ('supplier', 'location', 'process', 'role', 'person')),
                -- Exactly the column of its kind is set.
                ADD CONSTRAINT bia_dependencies_target_check CHECK (
                    (kind = 'supplier') = (supplier_id IS NOT NULL)
                    AND (kind = 'location') = (location_id IS NOT NULL)
                    AND (kind = 'process') = (upstream_process_id IS NOT NULL)
                    AND (kind = 'person') = (user_id IS NOT NULL)
                    AND (kind = 'role') = (role_name IS NOT NULL)
                ),
                ADD CONSTRAINT bia_dependencies_assessment_fk FOREIGN KEY (organization_id, assessment_id)
                    REFERENCES bia_assessments (organization_id, id) ON DELETE CASCADE,
                ADD CONSTRAINT bia_dependencies_supplier_fk FOREIGN KEY (organization_id, supplier_id)
                    REFERENCES core_suppliers (organization_id, id) ON DELETE CASCADE,
                ADD CONSTRAINT bia_dependencies_location_fk FOREIGN KEY (organization_id, location_id)
                    REFERENCES core_locations (organization_id, id) ON DELETE CASCADE,
                ADD CONSTRAINT bia_dependencies_process_fk FOREIGN KEY (organization_id, upstream_process_id)
                    REFERENCES bia_processes (organization_id, id) ON DELETE CASCADE
            SQL);

        foreach (['supplier_id', 'location_id', 'upstream_process_id', 'user_id'] as $column) {
            DB::statement("CREATE UNIQUE INDEX bia_dependencies_{$column}_unique ON bia_dependencies (assessment_id, {$column}) WHERE {$column} IS NOT NULL");
        }

        DB::statement('CREATE UNIQUE INDEX bia_dependencies_role_unique ON bia_dependencies (assessment_id, lower(role_name)) WHERE role_name IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('bia_dependencies');
    }
};
