<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Certificate expiry reminders already sent (8.1), one per certificate and
 * expiry date, so a renewed certificate is announced again (bookkeeping).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications_certificate_reminders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->cascadeOnDelete();
            $table->foreignUuid('application_id')->constrained('applications_applications')->cascadeOnDelete();
            $table->string('certificate');
            $table->date('expires_on');
            $table->timestampTz('sent_at');

            $table->unique(['application_id', 'certificate', 'expires_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications_certificate_reminders');
    }
};
