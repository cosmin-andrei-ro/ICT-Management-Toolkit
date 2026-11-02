<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The value of every indicator once a day (ADR 0045), for trends. Values are
 * figures, never records or personal data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reporting_metric_snapshots', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->cascadeOnDelete();
            $table->string('metric_key', 100);
            $table->date('taken_on');
            // Null when the module could not compute it (kept, so the gap shows in the chart).
            $table->decimal('value', 14, 2)->nullable();
            $table->timestampTz('created_at');

            $table->unique(['organization_id', 'metric_key', 'taken_on']);
            $table->index('taken_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporting_metric_snapshots');
    }
};
