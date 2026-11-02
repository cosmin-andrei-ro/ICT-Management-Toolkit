<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Risks suggested by the other modules (ADR 0033), one per situation (key),
 * kept so that a dismissed suggestion stays dismissed and a converted one
 * points to its risk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_suggestions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->cascadeOnDelete();
            $table->string('key');
            $table->string('source', 64);
            $table->string('source_label');
            $table->string('title');
            $table->text('description');
            $table->string('category', 32);
            $table->jsonb('assets')->default('[]');
            $table->jsonb('threats')->default('[]');
            $table->jsonb('vulnerabilities')->default('[]');
            $table->unsignedSmallInteger('likelihood')->nullable();
            $table->unsignedSmallInteger('impact')->nullable();
            $table->string('evidence_url', 2000)->nullable();
            $table->uuid('owner_id')->nullable();
            $table->string('status', 16)->default('open');
            $table->uuid('risk_id')->nullable();
            $table->string('dismissal_reason', 2000)->nullable();
            $table->foreignUuid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('decided_at')->nullable();
            $table->timestampTz('first_seen_at');
            $table->timestampTz('last_seen_at');

            $table->unique(['organization_id', 'key']);
            $table->index(['organization_id', 'status']);
        });
        DB::statement(<<<'SQL'
            ALTER TABLE risk_suggestions
                ADD CONSTRAINT risk_suggestions_status_check CHECK (status IN ('open', 'converted', 'dismissed', 'resolved')),
                ADD CONSTRAINT risk_suggestions_category_check CHECK (category IN ('information_security', 'availability', 'compliance', 'supplier', 'operational', 'project')),
                ADD CONSTRAINT risk_suggestions_converted_check CHECK ((status = 'converted') = (risk_id IS NOT NULL)),
                ADD CONSTRAINT risk_suggestions_dismissed_check CHECK ((status = 'dismissed') = (dismissal_reason IS NOT NULL)),
                ADD CONSTRAINT risk_suggestions_scores_check CHECK ((likelihood IS NULL OR likelihood BETWEEN 1 AND 5) AND (impact IS NULL OR impact BETWEEN 1 AND 5))
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_suggestions');
    }
};
