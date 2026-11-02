<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The CMDB item that stands for the application (created when its first
 * version comes into force, ADR 0030), referenced by id through Core's
 * ConfigurationItems contract; and the review reminders already sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications_applications', function (Blueprint $table): void {
            $table->uuid('configuration_item_id')->nullable();
            // Bookkeeping, not audited: the review date a reminder was sent for.
            $table->date('review_reminded_for')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('applications_applications', function (Blueprint $table): void {
            $table->dropColumn(['configuration_item_id', 'review_reminded_for']);
        });
    }
};
