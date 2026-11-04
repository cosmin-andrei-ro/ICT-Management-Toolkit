<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticketing_team_olas', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->cascadeOnDelete();
            $table->foreignUuid('team_id')->unique()->constrained('core_teams')->cascadeOnDelete();
            $table->unsignedInteger('minutes');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE ticketing_team_olas ADD CONSTRAINT ticketing_team_olas_minutes_check CHECK (minutes BETWEEN 1 AND 525600)');

        Schema::create('ticketing_ola_timers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->cascadeOnDelete();
            $table->foreignUuid('ticket_id')->constrained('ticketing_tickets')->cascadeOnDelete();
            // Kept when a team is deleted: the history of the ticket stays readable.
            $table->foreignUuid('team_id')->nullable()->constrained('core_teams')->nullOnDelete();
            $table->unsignedInteger('target_minutes');
            $table->unsignedInteger('consumed_minutes')->default(0);
            $table->timestampTz('running_since')->nullable();
            $table->timestampTz('due_at')->nullable();
            $table->timestampTz('breached_at')->nullable();
            $table->timestampTz('escalated_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->string('outcome', 16)->nullable();
            $table->timestampsTz();
        });
        DB::statement(<<<'SQL'
            ALTER TABLE ticketing_ola_timers
                ADD CONSTRAINT ticketing_ola_timers_outcome_check CHECK (outcome IS NULL OR outcome IN ('met', 'breached', 'cancelled')),
                ADD CONSTRAINT ticketing_ola_timers_target_positive CHECK (target_minutes > 0),
                ADD CONSTRAINT ticketing_ola_timers_running_has_due CHECK (running_since IS NULL OR due_at IS NOT NULL),
                ADD CONSTRAINT ticketing_ola_timers_completed_has_outcome CHECK ((completed_at IS NULL) = (outcome IS NULL)),
                ADD CONSTRAINT ticketing_ola_timers_completed_not_running CHECK (completed_at IS NULL OR running_since IS NULL)
            SQL);
        // A ticket is with one team at a time.
        DB::statement('CREATE UNIQUE INDEX ticketing_ola_timers_one_open ON ticketing_ola_timers (ticket_id) WHERE completed_at IS NULL');
        DB::statement('CREATE INDEX ticketing_ola_timers_due_index ON ticketing_ola_timers (due_at) WHERE running_since IS NOT NULL');

        Schema::table('ticketing_sla_timers', function (Blueprint $table): void {
            $table->timestampTz('escalated_at')->nullable();
        });

        Schema::table('ticketing_queues', function (Blueprint $table): void {
            $table->foreignUuid('escalation_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('escalate_after_minutes')->default(60);
        });
        DB::statement('ALTER TABLE ticketing_queues ADD CONSTRAINT ticketing_queues_escalate_after_check CHECK (escalate_after_minutes BETWEEN 1 AND 525600)');
    }

    public function down(): void
    {
        Schema::table('ticketing_queues', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('escalation_user_id');
            $table->dropColumn('escalate_after_minutes');
        });
        Schema::table('ticketing_sla_timers', function (Blueprint $table): void {
            $table->dropColumn('escalated_at');
        });
        Schema::dropIfExists('ticketing_ola_timers');
        Schema::dropIfExists('ticketing_team_olas');
    }
};
