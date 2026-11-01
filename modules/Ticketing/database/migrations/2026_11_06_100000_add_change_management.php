<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Change enablement (Stage 5, ADR 0014): changes are tickets of type "change" with
 * plans, a window and CAB authorization; standard changes come from pre-approved templates.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE ticketing_tickets DROP CONSTRAINT ticketing_tickets_type_check');
        DB::statement("ALTER TABLE ticketing_tickets ADD CONSTRAINT ticketing_tickets_type_check CHECK (type IN ('incident', 'service_request', 'problem', 'change'))");

        DB::statement('ALTER TABLE ticketing_tickets DROP CONSTRAINT ticketing_tickets_resolution_code_check');
        DB::statement("ALTER TABLE ticketing_tickets ADD CONSTRAINT ticketing_tickets_resolution_code_check CHECK (resolution_code IS NULL OR resolution_code IN ('fixed', 'workaround', 'fulfilled', 'not_reproducible', 'duplicate', 'user_error', 'no_action_needed', 'implemented', 'rolled_back'))");

        Schema::create('ticketing_cab_boards', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            // Emergency CAB: a small board that authorizes emergency changes (owner's decision).
            $table->boolean('is_emergency')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['organization_id', 'id']);
        });
        DB::statement('CREATE UNIQUE INDEX ticketing_cab_boards_name_unique ON ticketing_cab_boards (organization_id, lower(name)) WHERE deleted_at IS NULL');

        Schema::create('ticketing_cab_members', function (Blueprint $table): void {
            $table->foreignUuid('board_id')->constrained('ticketing_cab_boards')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestampsTz();

            $table->primary(['board_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('ticketing_change_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            $table->string('name');
            $table->text('description');
            $table->string('risk', 16)->default('low');
            $table->text('implementation_plan');
            $table->text('rollback_plan');
            $table->text('test_plan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['organization_id', 'id']);
        });
        DB::statement("ALTER TABLE ticketing_change_templates ADD CONSTRAINT ticketing_change_templates_risk_check CHECK (risk IN ('low', 'medium', 'high'))");
        DB::statement('CREATE UNIQUE INDEX ticketing_change_templates_name_unique ON ticketing_change_templates (organization_id, lower(name)) WHERE deleted_at IS NULL');

        Schema::create('ticketing_change_details', function (Blueprint $table): void {
            $table->foreignUuid('ticket_id')->primary()->constrained('ticketing_tickets')->cascadeOnDelete();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->cascadeOnDelete();
            $table->string('change_type', 16);
            $table->string('risk', 16);
            $table->uuid('cab_board_id')->nullable();
            $table->uuid('template_id')->nullable();
            $table->text('implementation_plan')->nullable();
            $table->text('rollback_plan')->nullable();
            $table->text('test_plan')->nullable();
            $table->timestampTz('planned_start')->nullable();
            $table->timestampTz('planned_end')->nullable();
            $table->string('approval_status', 16)->default('draft');
            // Incremented at each submission; decisions belong to one round.
            $table->unsignedInteger('approval_round')->default(0);
            $table->timestampTz('approved_at')->nullable();
            // Post-implementation review, required before an emergency change can close.
            $table->text('review')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();

            $table->index(['organization_id', 'planned_start']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE ticketing_change_details
                ADD CONSTRAINT ticketing_change_details_type_check CHECK (change_type IN ('standard', 'normal', 'emergency')),
                ADD CONSTRAINT ticketing_change_details_risk_check CHECK (risk IN ('low', 'medium', 'high')),
                ADD CONSTRAINT ticketing_change_details_approval_check CHECK (approval_status IN ('draft', 'pending', 'approved', 'rejected')),
                ADD CONSTRAINT ticketing_change_details_window_check CHECK (planned_end IS NULL OR planned_start IS NULL OR planned_end > planned_start),
                -- Standard changes are pre-approved through their template; the others need a CAB.
                ADD CONSTRAINT ticketing_change_details_standard_has_template CHECK (change_type <> 'standard' OR template_id IS NOT NULL),
                ADD CONSTRAINT ticketing_change_details_approved_has_timestamp CHECK ((approval_status = 'approved') = (approved_at IS NOT NULL)),
                ADD CONSTRAINT ticketing_change_details_review_complete CHECK ((review IS NULL) = (reviewed_at IS NULL)),
                ADD CONSTRAINT ticketing_change_details_cab_fk FOREIGN KEY (organization_id, cab_board_id)
                    REFERENCES ticketing_cab_boards (organization_id, id) ON DELETE SET NULL (cab_board_id),
                ADD CONSTRAINT ticketing_change_details_template_fk FOREIGN KEY (organization_id, template_id)
                    REFERENCES ticketing_change_templates (organization_id, id) ON DELETE RESTRICT
            SQL);

        Schema::create('ticketing_change_approvals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->cascadeOnDelete();
            $table->foreignUuid('ticket_id')->constrained('ticketing_tickets')->cascadeOnDelete();
            $table->unsignedInteger('round');
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->string('decision', 16);
            $table->text('comment')->nullable();
            $table->timestampTz('decided_at');

            $table->unique(['ticket_id', 'round', 'user_id']);
        });

        DB::statement("ALTER TABLE ticketing_change_approvals ADD CONSTRAINT ticketing_change_approvals_decision_check CHECK (decision IN ('approved', 'rejected'))");
        DB::statement("ALTER TABLE ticketing_change_approvals ADD CONSTRAINT ticketing_change_approvals_rejection_reason CHECK (decision <> 'rejected' OR (comment IS NOT NULL AND length(trim(comment)) > 0))");
    }

    public function down(): void
    {
        Schema::dropIfExists('ticketing_change_approvals');
        Schema::dropIfExists('ticketing_change_details');
        Schema::dropIfExists('ticketing_change_templates');
        Schema::dropIfExists('ticketing_cab_members');
        Schema::dropIfExists('ticketing_cab_boards');

        // Fail rather than silently drop change tickets or their resolution codes.
        DB::statement('ALTER TABLE ticketing_tickets DROP CONSTRAINT ticketing_tickets_resolution_code_check');
        DB::statement("ALTER TABLE ticketing_tickets ADD CONSTRAINT ticketing_tickets_resolution_code_check CHECK (resolution_code IS NULL OR resolution_code IN ('fixed', 'workaround', 'fulfilled', 'not_reproducible', 'duplicate', 'user_error', 'no_action_needed'))");
        DB::statement('ALTER TABLE ticketing_tickets DROP CONSTRAINT ticketing_tickets_type_check');
        DB::statement("ALTER TABLE ticketing_tickets ADD CONSTRAINT ticketing_tickets_type_check CHECK (type IN ('incident', 'service_request', 'problem'))");
    }
};
