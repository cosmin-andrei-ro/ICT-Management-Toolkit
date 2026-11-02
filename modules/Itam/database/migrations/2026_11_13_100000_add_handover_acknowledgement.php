<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The holder confirms in the portal that they received an asset (ADR 0021); the
 * confirmation is printed on the handover protocol.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('itam_asset_assignments', function (Blueprint $table): void {
            $table->timestampTz('acknowledged_at')->nullable();
            $table->string('acknowledged_ip', 45)->nullable();
        });

        DB::statement('ALTER TABLE itam_asset_assignments ADD CONSTRAINT itam_asset_assignments_acknowledged_check CHECK ((acknowledged_at IS NULL) = (acknowledged_ip IS NULL))');
        // "What is waiting for my confirmation" is asked on every visit to My equipment.
        DB::statement('CREATE INDEX itam_asset_assignments_pending_index ON itam_asset_assignments (user_id) WHERE acknowledged_at IS NULL AND returned_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS itam_asset_assignments_pending_index');
        DB::statement('ALTER TABLE itam_asset_assignments DROP CONSTRAINT IF EXISTS itam_asset_assignments_acknowledged_check');

        Schema::table('itam_asset_assignments', function (Blueprint $table): void {
            $table->dropColumn(['acknowledged_at', 'acknowledged_ip']);
        });
    }
};
