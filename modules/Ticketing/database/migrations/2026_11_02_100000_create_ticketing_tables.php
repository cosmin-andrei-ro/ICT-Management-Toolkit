<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ticketing core (Stage 2): incidents and service requests in one table, with
 * categories, queues routed to Core teams, a priority matrix and per-organization
 * numbering. See docs/adr/0008-ticketing-data-model.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticketing_categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            $table->uuid('parent_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['organization_id', 'id']);
            $table->index('parent_id');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE ticketing_categories ADD CONSTRAINT ticketing_categories_parent_fk
                FOREIGN KEY (organization_id, parent_id) REFERENCES ticketing_categories (organization_id, id)
                ON DELETE SET NULL (parent_id)
            SQL);
        DB::statement('CREATE UNIQUE INDEX ticketing_categories_name_unique ON ticketing_categories (organization_id, parent_id, lower(name)) NULLS NOT DISTINCT WHERE deleted_at IS NULL');

        Schema::create('ticketing_queues', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            $table->uuid('team_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['organization_id', 'id']);
            $table->index('team_id');
        });

        // The team must belong to the queue's organization.
        DB::statement(<<<'SQL'
            ALTER TABLE ticketing_queues ADD CONSTRAINT ticketing_queues_team_fk
                FOREIGN KEY (organization_id, team_id) REFERENCES core_teams (organization_id, id)
                ON DELETE RESTRICT
            SQL);
        DB::statement('CREATE UNIQUE INDEX ticketing_queues_name_unique ON ticketing_queues (organization_id, lower(name)) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX ticketing_queues_one_default ON ticketing_queues (organization_id) WHERE is_default AND deleted_at IS NULL');

        Schema::create('ticketing_priority_matrix', function (Blueprint $table): void {
            $table->foreignUuid('organization_id')->constrained('core_organizations')->cascadeOnDelete();
            $table->string('impact', 16);
            $table->string('urgency', 16);
            $table->string('priority', 16);
            $table->timestampsTz();

            $table->primary(['organization_id', 'impact', 'urgency']);
        });

        DB::statement("ALTER TABLE ticketing_priority_matrix ADD CONSTRAINT ticketing_priority_matrix_values_check CHECK (impact IN ('high', 'medium', 'low') AND urgency IN ('high', 'medium', 'low') AND priority IN ('critical', 'high', 'medium', 'low'))");

        Schema::create('ticketing_sequences', function (Blueprint $table): void {
            $table->foreignUuid('organization_id')->constrained('core_organizations')->cascadeOnDelete();
            $table->string('type', 32);
            $table->unsignedBigInteger('last_value');

            $table->primary(['organization_id', 'type']);
        });

        Schema::create('ticketing_tickets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            $table->string('number', 32);
            $table->string('type', 32);
            $table->string('title');
            $table->text('description');
            $table->string('status', 32);
            $table->string('impact', 16);
            $table->string('urgency', 16);
            $table->string('priority', 16);
            $table->string('channel', 16);
            $table->foreignUuid('requester_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('queue_id')->nullable();
            $table->uuid('category_id')->nullable();
            $table->timestampTz('first_responded_at')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->string('resolution_code', 32)->nullable();
            $table->text('resolution_note')->nullable();
            $table->unsignedInteger('reopened_count')->default(0);
            $table->jsonb('custom_fields')->default('{}');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['organization_id', 'number']);
            $table->index(['organization_id', 'status', 'priority']);
            $table->index(['organization_id', 'queue_id', 'status']);
            $table->index(['organization_id', 'assignee_id', 'status']);
            $table->index(['organization_id', 'requester_id', 'created_at']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE ticketing_tickets
                ADD CONSTRAINT ticketing_tickets_type_check CHECK (type IN ('incident', 'service_request')),
                ADD CONSTRAINT ticketing_tickets_status_check CHECK (status IN ('new', 'assigned', 'in_progress', 'pending', 'resolved', 'closed', 'cancelled')),
                ADD CONSTRAINT ticketing_tickets_impact_check CHECK (impact IN ('high', 'medium', 'low')),
                ADD CONSTRAINT ticketing_tickets_urgency_check CHECK (urgency IN ('high', 'medium', 'low')),
                ADD CONSTRAINT ticketing_tickets_priority_check CHECK (priority IN ('critical', 'high', 'medium', 'low')),
                ADD CONSTRAINT ticketing_tickets_channel_check CHECK (channel IN ('portal', 'agent', 'email', 'api', 'phone')),
                ADD CONSTRAINT ticketing_tickets_resolution_code_check CHECK (resolution_code IS NULL OR resolution_code IN ('fixed', 'workaround', 'fulfilled', 'not_reproducible', 'duplicate', 'user_error', 'no_action_needed')),
                -- Someone must own work in progress.
                ADD CONSTRAINT ticketing_tickets_assignee_required CHECK (status NOT IN ('assigned', 'in_progress') OR assignee_id IS NOT NULL),
                ADD CONSTRAINT ticketing_tickets_resolution_required CHECK (status NOT IN ('resolved', 'closed') OR (resolved_at IS NOT NULL AND resolution_code IS NOT NULL)),
                ADD CONSTRAINT ticketing_tickets_closed_at_required CHECK (status <> 'closed' OR closed_at IS NOT NULL),
                ADD CONSTRAINT ticketing_tickets_custom_fields_object CHECK (jsonb_typeof(custom_fields) = 'object'),
                ADD CONSTRAINT ticketing_tickets_queue_fk FOREIGN KEY (organization_id, queue_id)
                    REFERENCES ticketing_queues (organization_id, id) ON DELETE SET NULL (queue_id),
                ADD CONSTRAINT ticketing_tickets_category_fk FOREIGN KEY (organization_id, category_id)
                    REFERENCES ticketing_categories (organization_id, id) ON DELETE SET NULL (category_id)
            SQL);

        // Full-text search over number, title and description; 'simple' keeps ticket numbers intact.
        DB::statement(<<<'SQL'
            ALTER TABLE ticketing_tickets ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (
                setweight(to_tsvector('simple', number), 'A')
                || setweight(to_tsvector('english', title), 'A')
                || setweight(to_tsvector('english', description), 'B')
            ) STORED
            SQL);
        DB::statement('CREATE INDEX ticketing_tickets_search_vector_index ON ticketing_tickets USING GIN (search_vector)');

        Schema::create('ticketing_ticket_watchers', function (Blueprint $table): void {
            $table->foreignUuid('ticket_id')->constrained('ticketing_tickets')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestampsTz();

            $table->primary(['ticket_id', 'user_id']);
            $table->index('user_id');
        });

        // Status history for reporting (time in each status); the audit log keeps the full record.
        Schema::create('ticketing_status_transitions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->cascadeOnDelete();
            $table->foreignUuid('ticket_id')->constrained('ticketing_tickets')->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticketing_status_transitions');
        Schema::dropIfExists('ticketing_ticket_watchers');
        Schema::dropIfExists('ticketing_tickets');
        Schema::dropIfExists('ticketing_sequences');
        Schema::dropIfExists('ticketing_priority_matrix');
        Schema::dropIfExists('ticketing_queues');
        Schema::dropIfExists('ticketing_categories');
    }
};
