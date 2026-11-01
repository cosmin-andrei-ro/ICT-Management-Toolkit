<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SLA (Stage 3, ADR 0010): per-priority targets in working minutes and one timer
 * per ticket and metric (response, resolution).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Overrides only; missing priorities use the defaults in SlaTargets.
        Schema::create('ticketing_sla_targets', function (Blueprint $table): void {
            $table->foreignUuid('organization_id')->constrained('core_organizations')->cascadeOnDelete();
            $table->string('priority', 16);
            $table->unsignedInteger('response_minutes');
            $table->unsignedInteger('resolution_minutes');
            $table->timestampsTz();

            $table->primary(['organization_id', 'priority']);
        });

        DB::statement("ALTER TABLE ticketing_sla_targets ADD CONSTRAINT ticketing_sla_targets_values_check CHECK (priority IN ('critical', 'high', 'medium', 'low') AND response_minutes > 0 AND resolution_minutes >= response_minutes)");

        Schema::create('ticketing_sla_timers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->cascadeOnDelete();
            $table->foreignUuid('ticket_id')->constrained('ticketing_tickets')->cascadeOnDelete();
            $table->string('metric', 16);
            $table->unsignedInteger('target_minutes');
            // Working minutes used before the current run (pauses do not count).
            $table->unsignedInteger('consumed_minutes')->default(0);
            // Set while the clock runs; null while paused or completed.
            $table->timestampTz('running_since')->nullable();
            $table->timestampTz('due_at')->nullable();
            $table->timestampTz('warn_at')->nullable();
            $table->timestampTz('warned_at')->nullable();
            $table->timestampTz('breached_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->string('outcome', 16)->nullable();
            $table->timestampsTz();

            $table->unique(['ticket_id', 'metric']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE ticketing_sla_timers
                ADD CONSTRAINT ticketing_sla_timers_metric_check CHECK (metric IN ('response', 'resolution')),
                ADD CONSTRAINT ticketing_sla_timers_outcome_check CHECK (outcome IS NULL OR outcome IN ('met', 'breached', 'cancelled')),
                ADD CONSTRAINT ticketing_sla_timers_target_positive CHECK (target_minutes > 0),
                ADD CONSTRAINT ticketing_sla_timers_running_has_due CHECK (running_since IS NULL OR due_at IS NOT NULL),
                ADD CONSTRAINT ticketing_sla_timers_completed_has_outcome CHECK ((completed_at IS NULL) = (outcome IS NULL)),
                ADD CONSTRAINT ticketing_sla_timers_completed_not_running CHECK (completed_at IS NULL OR running_since IS NULL)
            SQL);

        // The deadline job scans only running clocks.
        DB::statement('CREATE INDEX ticketing_sla_timers_due_index ON ticketing_sla_timers (due_at) WHERE running_since IS NOT NULL AND breached_at IS NULL');
        DB::statement('CREATE INDEX ticketing_sla_timers_warn_index ON ticketing_sla_timers (warn_at) WHERE running_since IS NOT NULL AND warned_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('ticketing_sla_timers');
        Schema::dropIfExists('ticketing_sla_targets');
    }
};
