<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An API token works in the organization it was issued for (ADR 0061), not in
 * whichever organization its user last switched to in the browser. Tokens
 * issued before keep following the user's current organization.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->foreignUuid('organization_id')->nullable()->constrained('core_organizations')->cascadeOnDelete();
            $table->index(['organization_id', 'tokenable_id']);
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('organization_id');
        });
    }
};
