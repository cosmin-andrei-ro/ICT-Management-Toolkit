<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spaces the toolkit fills itself (ADR 0043): "applications" holds the page
 * of every documented application. One per organization.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentation_spaces', function (Blueprint $table): void {
            $table->string('purpose', 32)->nullable();
        });
        DB::statement('CREATE UNIQUE INDEX documentation_spaces_purpose_unique ON documentation_spaces (organization_id, purpose) WHERE purpose IS NOT NULL AND deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX documentation_spaces_purpose_unique');

        Schema::table('documentation_spaces', function (Blueprint $table): void {
            $table->dropColumn('purpose');
        });
    }
};
