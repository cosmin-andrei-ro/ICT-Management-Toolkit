<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How each person arranged their dashboard (ADR 0044): the indicators they
 * hid and the order of the module groups. A personal setting, not a record
 * of the organization, so it carries no audit trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reporting_dashboard_preferences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            // Metric keys ("ticketing.open"); keys of metrics removed later are ignored.
            $table->jsonb('hidden_metrics')->default('[]');
            // Source keys ("ticketing") in the chosen order; sources not listed follow.
            $table->jsonb('source_order')->default('[]');
            $table->timestampsTz();

            $table->unique(['organization_id', 'user_id']);
        });
        DB::statement("ALTER TABLE reporting_dashboard_preferences ADD CONSTRAINT reporting_dashboard_preferences_arrays CHECK (jsonb_typeof(hidden_metrics) = 'array' AND jsonb_typeof(source_order) = 'array')");
    }

    public function down(): void
    {
        Schema::dropIfExists('reporting_dashboard_preferences');
    }
};
