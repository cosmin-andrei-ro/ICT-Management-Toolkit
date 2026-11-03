<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Outbound webhooks (ADR 0066): where the organization's events are sent,
 * and every delivery attempt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_webhooks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('url', 2048);
            // Encrypted with the application key; signs every delivery.
            $table->text('secret');
            // Event types the endpoint receives, e.g. ["ticket.created"].
            $table->jsonb('events')->default('[]');
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['organization_id', 'is_active']);
        });

        Schema::create('core_webhook_deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->cascadeOnDelete();
            $table->foreignUuid('webhook_id')->constrained('core_webhooks')->cascadeOnDelete();
            $table->string('event', 100);
            $table->jsonb('payload');
            $table->string('status', 16)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestampTz('next_attempt_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampsTz();

            $table->index(['webhook_id', 'created_at']);
            $table->index(['organization_id', 'created_at']);
        });

        DB::statement("ALTER TABLE core_webhook_deliveries ADD CONSTRAINT core_webhook_deliveries_status_check CHECK (status IN ('pending', 'delivered', 'failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('core_webhook_deliveries');
        Schema::dropIfExists('core_webhooks');
    }
};
