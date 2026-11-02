<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The review date an owner was reminded of (ADR 0025): each date is announced
 * once, a new one again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bia_processes', function (Blueprint $table): void {
            $table->date('review_reminded_for')->nullable();
            $table->index(['organization_id', 'next_review_on']);
        });
    }

    public function down(): void
    {
        Schema::table('bia_processes', function (Blueprint $table): void {
            $table->dropIndex(['organization_id', 'next_review_on']);
            $table->dropColumn('review_reminded_for');
        });
    }
};
