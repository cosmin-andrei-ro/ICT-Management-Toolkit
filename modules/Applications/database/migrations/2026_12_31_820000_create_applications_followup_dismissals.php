<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications_followup_dismissals', function (Blueprint $table): void {
            $table->foreignUuid('application_id')->constrained('applications_applications')->cascadeOnDelete();
            $table->string('key', 80);
            $table->foreignUuid('dismissed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->nullable();

            $table->primary(['application_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications_followup_dismissals');
    }
};
