<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Problem management (Stage 4, ADR 0012): problems are tickets of type "problem"
 * with analysis details; incidents point to the problem behind them.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE ticketing_tickets DROP CONSTRAINT ticketing_tickets_type_check');
        DB::statement("ALTER TABLE ticketing_tickets ADD CONSTRAINT ticketing_tickets_type_check CHECK (type IN ('incident', 'service_request', 'problem'))");

        Schema::table('ticketing_tickets', function (Blueprint $table): void {
            $table->uuid('problem_id')->nullable();
            $table->index('problem_id');
            // Target of the same-organization foreign key below.
            $table->unique(['organization_id', 'id']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE ticketing_tickets
                ADD CONSTRAINT ticketing_tickets_problem_fk FOREIGN KEY (organization_id, problem_id)
                    REFERENCES ticketing_tickets (organization_id, id) ON DELETE SET NULL (problem_id),
                ADD CONSTRAINT ticketing_tickets_problem_only_on_incidents CHECK (problem_id IS NULL OR type = 'incident')
            SQL);

        // A row cannot check another row with CHECK: the linked ticket must be a problem.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION ticketing_tickets_problem_is_problem() RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.problem_id IS NOT NULL AND NOT EXISTS (
                    SELECT 1 FROM ticketing_tickets WHERE id = NEW.problem_id AND type = 'problem'
                ) THEN
                    RAISE EXCEPTION 'ticketing_tickets.problem_id must reference a problem'
                        USING ERRCODE = 'check_violation';
                END IF;
                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER ticketing_tickets_problem_is_problem
                BEFORE INSERT OR UPDATE OF problem_id ON ticketing_tickets
                FOR EACH ROW EXECUTE FUNCTION ticketing_tickets_problem_is_problem();
            SQL);

        Schema::create('ticketing_problem_details', function (Blueprint $table): void {
            $table->foreignUuid('ticket_id')->primary()->constrained('ticketing_tickets')->cascadeOnDelete();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->cascadeOnDelete();
            $table->text('root_cause')->nullable();
            $table->text('workaround')->nullable();
            // Set when the root cause and a workaround are documented (a "known error").
            $table->timestampTz('known_error_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE ticketing_problem_details ADD CONSTRAINT ticketing_problem_details_known_error_needs_workaround CHECK (known_error_at IS NULL OR (workaround IS NOT NULL AND length(trim(workaround)) > 0))');
        DB::statement('CREATE INDEX ticketing_problem_details_known_errors ON ticketing_problem_details (organization_id) WHERE known_error_at IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('ticketing_problem_details');

        DB::unprepared('DROP TRIGGER IF EXISTS ticketing_tickets_problem_is_problem ON ticketing_tickets; DROP FUNCTION IF EXISTS ticketing_tickets_problem_is_problem();');
        DB::statement('ALTER TABLE ticketing_tickets DROP CONSTRAINT ticketing_tickets_problem_only_on_incidents');
        DB::statement('ALTER TABLE ticketing_tickets DROP CONSTRAINT ticketing_tickets_problem_fk');

        Schema::table('ticketing_tickets', function (Blueprint $table): void {
            $table->dropIndex(['problem_id']);
            $table->dropUnique(['organization_id', 'id']);
            $table->dropColumn('problem_id');
        });

        // Fails if problem tickets exist, instead of silently dropping them.
        DB::statement('ALTER TABLE ticketing_tickets DROP CONSTRAINT ticketing_tickets_type_check');
        DB::statement("ALTER TABLE ticketing_tickets ADD CONSTRAINT ticketing_tickets_type_check CHECK (type IN ('incident', 'service_request'))");
    }
};
