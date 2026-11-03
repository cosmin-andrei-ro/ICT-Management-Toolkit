<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Closed tickets past the organization's retention period are anonymized
 * (ADR 0060): they keep their number, type, category, service, queue, dates
 * and SLA, but lose their requester and their text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticketing_tickets', function (Blueprint $table): void {
            // Dropping NOT NULL is a catalog change only: no table rewrite.
            $table->uuid('requester_id')->nullable()->change();
            $table->timestampTz('anonymized_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ticketing_tickets', function (Blueprint $table): void {
            $table->dropColumn('anonymized_at');
        });
        // Anonymized tickets have no requester left: the column stays nullable.
    }
};
