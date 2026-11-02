<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What IT can deliver when an item fails (ADR 0024): how fast it can be back
 * and how much recent data could be lost. The BIA compares it with what the
 * business requires.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cmdb_configuration_items', function (Blueprint $table): void {
            $table->unsignedInteger('recovery_time_minutes')->nullable();
            $table->unsignedInteger('recovery_point_minutes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cmdb_configuration_items', function (Blueprint $table): void {
            $table->dropColumn(['recovery_time_minutes', 'recovery_point_minutes']);
        });
    }
};
