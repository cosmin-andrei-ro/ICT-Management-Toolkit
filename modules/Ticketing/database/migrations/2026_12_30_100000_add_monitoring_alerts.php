<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alerts from monitoring tools open incidents (ADR 0067). One row per alert
 * key while it fires; repeats are counted on it instead of opening tickets.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE ticketing_tickets DROP CONSTRAINT ticketing_tickets_channel_check');
        DB::statement("ALTER TABLE ticketing_tickets ADD CONSTRAINT ticketing_tickets_channel_check CHECK (channel IN ('portal', 'agent', 'email', 'api', 'phone', 'monitoring')) NOT VALID");
        DB::statement('ALTER TABLE ticketing_tickets VALIDATE CONSTRAINT ticketing_tickets_channel_check');

        Schema::create('ticketing_monitoring_alerts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            $table->string('key', 255);
            $table->string('source', 100)->nullable();
            $table->string('status', 20);
            $table->uuid('ticket_id');
            // The CMDB item matched, if any: the link itself lives with the CMDB.
            $table->uuid('configuration_item_id')->nullable();
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestampTz('first_seen_at');
            $table->timestampTz('last_seen_at');
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();

            $table->index(['organization_id', 'key']);
            $table->index('ticket_id');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE ticketing_monitoring_alerts
                ADD CONSTRAINT ticketing_monitoring_alerts_status_check CHECK (status IN ('firing', 'resolved')),
                ADD CONSTRAINT ticketing_monitoring_alerts_resolved_check CHECK ((status = 'resolved') = (resolved_at IS NOT NULL)),
                ADD CONSTRAINT ticketing_monitoring_alerts_occurrences_check CHECK (occurrences >= 1),
                ADD CONSTRAINT ticketing_monitoring_alerts_ticket_fk FOREIGN KEY (organization_id, ticket_id)
                    REFERENCES ticketing_tickets (organization_id, id) ON DELETE CASCADE
            SQL);
        // One firing alert per key: a repeat finds it instead of opening another incident.
        DB::statement("CREATE UNIQUE INDEX ticketing_monitoring_alerts_firing_unique ON ticketing_monitoring_alerts (organization_id, key) WHERE status = 'firing'");
    }

    public function down(): void
    {
        Schema::dropIfExists('ticketing_monitoring_alerts');
        DB::statement('ALTER TABLE ticketing_tickets DROP CONSTRAINT ticketing_tickets_channel_check');
        DB::statement("ALTER TABLE ticketing_tickets ADD CONSTRAINT ticketing_tickets_channel_check CHECK (channel IN ('portal', 'agent', 'email', 'api', 'phone'))");
    }
};
