<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The last time a recovery test actually brought the item back (ADR 0027): the
 * declared recovery capability is a claim, this is the evidence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cmdb_configuration_items', function (Blueprint $table): void {
            $table->date('recovery_verified_on')->nullable();
            $table->unsignedInteger('recovery_verified_minutes')->nullable();
            $table->string('recovery_verified_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cmdb_configuration_items', function (Blueprint $table): void {
            $table->dropColumn(['recovery_verified_on', 'recovery_verified_minutes', 'recovery_verified_by']);
        });
    }
};
