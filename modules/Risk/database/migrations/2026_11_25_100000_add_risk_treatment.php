<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Risk treatment (ADR 0032): the option chosen, the plan and the residual
 * risk; actions with owners and due dates (optionally tracked in the service
 * desk, referenced by id); acceptances of the residual risk, approved by a
 * risk manager above the organization's threshold; and the history of scores.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('risk_risks', function (Blueprint $table): void {
            $table->string('treatment', 16)->nullable();
            $table->text('treatment_plan')->nullable();
            $table->unsignedSmallInteger('residual_likelihood')->nullable();
            $table->unsignedSmallInteger('residual_impact')->nullable();
            $table->timestampTz('assessed_at')->nullable();
            // While an acceptance of the residual risk is valid.
            $table->date('accepted_until')->nullable();
            // Bookkeeping, not audited: the review date a reminder was sent for.
            $table->date('review_reminded_for')->nullable();
        });
        DB::statement(<<<'SQL'
            ALTER TABLE risk_risks
                ADD CONSTRAINT risk_risks_treatment_check CHECK (treatment IS NULL OR treatment IN ('mitigate', 'transfer', 'avoid', 'accept')),
                ADD CONSTRAINT risk_risks_residual_likelihood_check CHECK (residual_likelihood BETWEEN 1 AND 5),
                ADD CONSTRAINT risk_risks_residual_impact_check CHECK (residual_impact BETWEEN 1 AND 5),
                ADD CONSTRAINT risk_risks_residual_scored_check CHECK ((residual_likelihood IS NULL) = (residual_impact IS NULL))
            SQL);

        Schema::create('risk_actions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('risk_id');
            $table->string('title');
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_on')->nullable();
            $table->string('status', 16)->default('planned');
            $table->timestampTz('completed_at')->nullable();
            // The problem raised in Ticketing (through Core's ProblemTickets contract).
            $table->uuid('ticket_id')->nullable();
            $table->string('ticket_number', 32)->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index('risk_id');
            $table->index(['owner_id', 'status']);
        });
        DB::statement(<<<'SQL'
            ALTER TABLE risk_actions
                ADD CONSTRAINT risk_actions_status_check CHECK (status IN ('planned', 'in_progress', 'done', 'cancelled')),
                ADD CONSTRAINT risk_actions_completed_check CHECK ((status = 'done') = (completed_at IS NOT NULL)),
                ADD CONSTRAINT risk_actions_risk_fk FOREIGN KEY (organization_id, risk_id) REFERENCES risk_risks (organization_id, id) ON DELETE CASCADE
            SQL);

        Schema::create('risk_acceptances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('risk_id');
            // The residual risk accepted, as it was.
            $table->unsignedSmallInteger('likelihood');
            $table->unsignedSmallInteger('impact');
            $table->text('justification');
            $table->string('status', 16);
            $table->foreignUuid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('requested_at');
            $table->foreignUuid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('decided_at')->nullable();
            $table->string('comment', 2000)->nullable();
            $table->date('valid_until')->nullable();

            $table->index('risk_id');
        });
        DB::statement(<<<'SQL'
            ALTER TABLE risk_acceptances
                ADD CONSTRAINT risk_acceptances_status_check CHECK (status IN ('pending', 'approved', 'rejected', 'withdrawn', 'lapsed')),
                ADD CONSTRAINT risk_acceptances_decided_check CHECK ((status IN ('approved', 'rejected', 'lapsed')) = (decided_at IS NOT NULL)),
                ADD CONSTRAINT risk_acceptances_risk_fk FOREIGN KEY (organization_id, risk_id) REFERENCES risk_risks (organization_id, id) ON DELETE CASCADE
            SQL);
        DB::statement("CREATE UNIQUE INDEX risk_acceptances_pending_unique ON risk_acceptances (risk_id) WHERE status = 'pending'");

        // Every change of the scores, append-only, for the trend.
        Schema::create('risk_score_history', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('risk_id');
            $table->unsignedSmallInteger('inherent_likelihood')->nullable();
            $table->unsignedSmallInteger('inherent_impact')->nullable();
            $table->unsignedSmallInteger('residual_likelihood')->nullable();
            $table->unsignedSmallInteger('residual_impact')->nullable();
            $table->foreignUuid('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('recorded_at');

            $table->index(['risk_id', 'recorded_at']);
        });
        DB::statement('ALTER TABLE risk_score_history ADD CONSTRAINT risk_score_history_risk_fk FOREIGN KEY (organization_id, risk_id) REFERENCES risk_risks (organization_id, id) ON DELETE CASCADE');
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_score_history');
        Schema::dropIfExists('risk_acceptances');
        Schema::dropIfExists('risk_actions');

        Schema::table('risk_risks', function (Blueprint $table): void {
            $table->dropColumn(['treatment', 'treatment_plan', 'residual_likelihood', 'residual_impact', 'assessed_at', 'accepted_until', 'review_reminded_for']);
        });
    }
};
