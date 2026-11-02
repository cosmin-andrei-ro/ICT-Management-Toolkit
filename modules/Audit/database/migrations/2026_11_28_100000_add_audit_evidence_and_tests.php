<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Compliance evidence and control tests (ADR 0035): what shows a control
 * works - records of the toolkit, documents - and its periodic tests.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_controls', function (Blueprint $table): void {
            $table->unsignedSmallInteger('test_interval_months')->nullable();
            $table->date('last_tested_on')->nullable();
            $table->date('next_test_on')->nullable();
            // Reminder bookkeeping: the due date the owner was reminded of.
            $table->date('test_reminded_for')->nullable();

            $table->index('next_test_on');
        });
        DB::statement('ALTER TABLE audit_controls ADD CONSTRAINT audit_controls_test_interval_check CHECK (test_interval_months BETWEEN 1 AND 60)');

        Schema::create('audit_control_tests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('control_id');
            $table->date('tested_on');
            $table->foreignUuid('tester_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('result', 24);
            // How the control was tested (sample, walkthrough, technical check) and what was found.
            $table->text('method');
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->index(['control_id', 'tested_on']);
        });
        DB::statement('ALTER TABLE audit_control_tests ADD CONSTRAINT audit_control_tests_control_fk FOREIGN KEY (organization_id, control_id) REFERENCES audit_controls (organization_id, id) ON DELETE CASCADE');
        DB::statement("ALTER TABLE audit_control_tests ADD CONSTRAINT audit_control_tests_result_check CHECK (result IN ('effective', 'partially_effective', 'ineffective'))");

        Schema::create('audit_evidence', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('control_id');
            // A document recorded with a test.
            $table->foreignUuid('test_id')->nullable()->constrained('audit_control_tests')->nullOnDelete();
            $table->string('kind', 8);
            $table->string('title');
            $table->text('description')->nullable();
            // A record of another module (morph type and id, no foreign key: modules stay apart).
            $table->string('linked_type', 64)->nullable();
            $table->uuid('linked_id')->nullable();
            $table->date('valid_until')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['organization_id', 'id']);
            $table->index('control_id');
            $table->index(['linked_type', 'linked_id']);
        });
        DB::statement('ALTER TABLE audit_evidence ADD CONSTRAINT audit_evidence_control_fk FOREIGN KEY (organization_id, control_id) REFERENCES audit_controls (organization_id, id) ON DELETE CASCADE');
        DB::statement(<<<'SQL'
            ALTER TABLE audit_evidence ADD CONSTRAINT audit_evidence_kind_check CHECK (
                (kind = 'link' AND linked_type IS NOT NULL AND linked_id IS NOT NULL)
                OR (kind = 'file' AND linked_type IS NULL AND linked_id IS NULL)
            )
            SQL);
        DB::statement('CREATE UNIQUE INDEX audit_evidence_link_unique ON audit_evidence (control_id, linked_type, linked_id) WHERE deleted_at IS NULL AND kind = \'link\'');
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_evidence');
        Schema::dropIfExists('audit_control_tests');

        Schema::table('audit_controls', function (Blueprint $table): void {
            $table->dropIndex(['next_test_on']);
        });
        DB::statement('ALTER TABLE audit_controls DROP CONSTRAINT audit_controls_test_interval_check');

        Schema::table('audit_controls', function (Blueprint $table): void {
            $table->dropColumn(['test_interval_months', 'last_tested_on', 'next_test_on', 'test_reminded_for']);
        });
    }
};
