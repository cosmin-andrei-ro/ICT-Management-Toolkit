<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audits, their findings and corrective actions (ADR 0036).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_audits', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            // AUD-001, per organization.
            $table->unsignedInteger('number');
            $table->string('title');
            $table->string('kind', 16);
            $table->text('scope')->nullable();
            $table->foreignUuid('lead_auditor_id')->nullable()->constrained('users')->nullOnDelete();
            // Certification body or audit firm, for external audits.
            $table->string('audit_body')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('status', 16)->default('planned');
            $table->text('conclusion')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(['organization_id', 'number']);
        });
        DB::statement(<<<'SQL'
            ALTER TABLE audit_audits
                ADD CONSTRAINT audit_audits_kind_check CHECK (kind IN ('internal', 'external')),
                ADD CONSTRAINT audit_audits_status_check CHECK (status IN ('planned', 'in_progress', 'completed', 'cancelled')),
                ADD CONSTRAINT audit_audits_completed_check CHECK ((status = 'completed') = (completed_at IS NOT NULL)),
                ADD CONSTRAINT audit_audits_dates_check CHECK (ends_on IS NULL OR ends_on >= starts_on)
            SQL);

        // The frameworks an audit covers.
        Schema::create('audit_audit_framework', function (Blueprint $table): void {
            $table->uuid('audit_id');
            $table->uuid('framework_id');
            $table->uuid('organization_id');

            $table->primary(['audit_id', 'framework_id']);
        });
        DB::statement(<<<'SQL'
            ALTER TABLE audit_audit_framework
                ADD CONSTRAINT audit_audit_framework_audit_fk FOREIGN KEY (organization_id, audit_id) REFERENCES audit_audits (organization_id, id) ON DELETE CASCADE,
                ADD CONSTRAINT audit_audit_framework_framework_fk FOREIGN KEY (organization_id, framework_id) REFERENCES audit_frameworks (organization_id, id) ON DELETE CASCADE
            SQL);

        Schema::create('audit_findings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            // F-001, per organization.
            $table->unsignedInteger('number');
            // Raised in an audit, or from a control test that was not effective.
            $table->foreignUuid('audit_id')->nullable()->constrained('audit_audits')->nullOnDelete();
            $table->foreignUuid('test_id')->nullable()->constrained('audit_control_tests')->nullOnDelete();
            $table->foreignUuid('control_id')->nullable()->constrained('audit_controls')->nullOnDelete();
            $table->foreignUuid('requirement_id')->nullable()->constrained('audit_requirements')->nullOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('severity', 24);
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_on')->nullable();
            $table->text('root_cause')->nullable();
            $table->string('status', 24)->default('open');
            $table->foreignUuid('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('submitted_at')->nullable();
            // Closure verified by someone other than the owner (owner's decision).
            $table->foreignUuid('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('closed_at')->nullable();
            $table->text('verification_notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(['organization_id', 'number']);
            $table->index('audit_id');
            $table->index('control_id');
            $table->index(['owner_id', 'status']);
        });
        DB::statement(<<<'SQL'
            ALTER TABLE audit_findings
                ADD CONSTRAINT audit_findings_severity_check CHECK (severity IN ('major_nonconformity', 'minor_nonconformity', 'observation', 'opportunity')),
                ADD CONSTRAINT audit_findings_status_check CHECK (status IN ('open', 'verification', 'closed')),
                ADD CONSTRAINT audit_findings_closed_check CHECK ((status = 'closed') = (closed_at IS NOT NULL))
            SQL);

        Schema::create('audit_finding_actions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('finding_id');
            $table->string('title');
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_on')->nullable();
            $table->string('status', 16)->default('planned');
            $table->timestampTz('completed_at')->nullable();
            // The problem raised in Ticketing (through Core's ProblemTickets contract).
            $table->uuid('ticket_id')->nullable();
            $table->string('ticket_number', 32)->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index('finding_id');
            $table->index(['owner_id', 'status']);
        });
        DB::statement(<<<'SQL'
            ALTER TABLE audit_finding_actions
                ADD CONSTRAINT audit_finding_actions_status_check CHECK (status IN ('planned', 'in_progress', 'done', 'cancelled')),
                ADD CONSTRAINT audit_finding_actions_completed_check CHECK ((status = 'done') = (completed_at IS NOT NULL)),
                ADD CONSTRAINT audit_finding_actions_finding_fk FOREIGN KEY (organization_id, finding_id) REFERENCES audit_findings (organization_id, id) ON DELETE CASCADE
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_finding_actions');
        Schema::dropIfExists('audit_findings');
        Schema::dropIfExists('audit_audit_framework');
        Schema::dropIfExists('audit_audits');
    }
};
