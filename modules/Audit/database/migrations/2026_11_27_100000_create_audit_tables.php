<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Compliance (ADR 0034): frameworks and their requirements, the
 * organization's own controls mapped to requirements of any framework, and
 * the applicability of each requirement (the Statement of Applicability for
 * ISO/IEC 27001).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_frameworks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->cascadeOnDelete();
            // iso27001, nis2, gdpr, dora for the built-in ones; a slug for the organization's own.
            $table->string('key', 64);
            $table->string('name');
            $table->string('version', 64)->nullable();
            $table->text('description')->nullable();
            $table->boolean('has_soa')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(['organization_id', 'key']);
        });

        Schema::create('audit_requirements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('framework_id');
            $table->string('reference', 64);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('group')->nullable();
            $table->unsignedInteger('position')->default(0);
            // Applicability (the Statement of Applicability for ISO/IEC 27001): null until decided.
            $table->boolean('is_applicable')->nullable();
            $table->text('justification')->nullable();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(['framework_id', 'reference']);
        });
        DB::statement('ALTER TABLE audit_requirements ADD CONSTRAINT audit_requirements_framework_fk FOREIGN KEY (organization_id, framework_id) REFERENCES audit_frameworks (organization_id, id) ON DELETE CASCADE');

        Schema::create('audit_controls', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            // C-001, per organization.
            $table->unsignedInteger('number');
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('planned');
            $table->text('implementation_notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(['organization_id', 'number']);
            $table->index('owner_id');
        });
        DB::statement("ALTER TABLE audit_controls ADD CONSTRAINT audit_controls_status_check CHECK (status IN ('planned', 'partial', 'implemented', 'retired'))");
        DB::statement('CREATE UNIQUE INDEX audit_controls_name_unique ON audit_controls (organization_id, lower(name)) WHERE deleted_at IS NULL');

        // Which requirements a control helps to meet: one control, many frameworks.
        Schema::create('audit_control_requirement', function (Blueprint $table): void {
            $table->uuid('control_id');
            $table->uuid('requirement_id');
            $table->uuid('organization_id');

            $table->primary(['control_id', 'requirement_id']);
            $table->index('requirement_id');
        });
        DB::statement(<<<'SQL'
            ALTER TABLE audit_control_requirement
                ADD CONSTRAINT audit_control_requirement_control_fk FOREIGN KEY (organization_id, control_id) REFERENCES audit_controls (organization_id, id) ON DELETE CASCADE,
                ADD CONSTRAINT audit_control_requirement_requirement_fk FOREIGN KEY (organization_id, requirement_id) REFERENCES audit_requirements (organization_id, id) ON DELETE CASCADE
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_control_requirement');
        Schema::dropIfExists('audit_controls');
        Schema::dropIfExists('audit_requirements');
        Schema::dropIfExists('audit_frameworks');
    }
};
