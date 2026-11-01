<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Working hours and holidays, used for SLA deadlines (Ticketing) and later for
 * OLAs and continuity plans. An organization without a calendar uses the built-in
 * default (Monday–Friday 09–17, Europe/Bucharest, Romanian public holidays).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_business_calendars', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            $table->string('name');
            $table->string('timezone', 64);
            // ISO weekday (1 = Monday) => list of ["HH:MM", "HH:MM"] intervals.
            $table->jsonb('weekly_hours');
            $table->string('public_holidays', 8)->nullable();
            $table->boolean('is_default')->default(false);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        DB::statement("ALTER TABLE core_business_calendars ADD CONSTRAINT core_business_calendars_public_holidays_check CHECK (public_holidays IS NULL OR public_holidays IN ('RO'))");
        DB::statement("ALTER TABLE core_business_calendars ADD CONSTRAINT core_business_calendars_weekly_hours_object CHECK (jsonb_typeof(weekly_hours) = 'object')");
        DB::statement('CREATE UNIQUE INDEX core_business_calendars_one_default ON core_business_calendars (organization_id) WHERE is_default AND deleted_at IS NULL');

        Schema::create('core_calendar_holidays', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('calendar_id')->constrained('core_business_calendars')->cascadeOnDelete();
            $table->date('date');
            $table->string('name');
            $table->timestampsTz();

            $table->unique(['calendar_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_calendar_holidays');
        Schema::dropIfExists('core_business_calendars');
    }
};
