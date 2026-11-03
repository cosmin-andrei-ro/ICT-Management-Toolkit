<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A project's approved budget and its costs by category (ADR 0055); one
 * currency per project, no timesheets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects_projects', function (Blueprint $table): void {
            $table->decimal('budget_amount', 14, 2)->nullable()->after('planned_end');
            $table->char('currency', 3)->nullable()->after('budget_amount');
        });
        DB::statement('ALTER TABLE projects_projects ADD CONSTRAINT projects_projects_budget_check CHECK (budget_amount IS NULL OR (budget_amount >= 0 AND currency IS NOT NULL))');

        Schema::create('projects_costs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('project_id');
            $table->string('category', 16);
            $table->string('description', 255);
            // In the project's currency; negative for a credit note.
            $table->decimal('amount', 14, 2);
            $table->date('incurred_on');
            $table->uuid('supplier_id')->nullable();
            $table->string('invoice_number', 100)->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['project_id', 'incurred_on']);
        });
        DB::statement('ALTER TABLE projects_costs ADD CONSTRAINT projects_costs_project_fk FOREIGN KEY (organization_id, project_id) REFERENCES projects_projects (organization_id, id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE projects_costs ADD CONSTRAINT projects_costs_supplier_fk FOREIGN KEY (organization_id, supplier_id) REFERENCES core_suppliers (organization_id, id) ON DELETE SET NULL (supplier_id)');
        DB::statement("ALTER TABLE projects_costs ADD CONSTRAINT projects_costs_category_check CHECK (category IN ('external_labour', 'licences', 'hardware', 'services', 'other'))");
        DB::statement('ALTER TABLE projects_costs ADD CONSTRAINT projects_costs_amount_check CHECK (amount <> 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('projects_costs');
        Schema::table('projects_projects', function (Blueprint $table): void {
            $table->dropColumn(['budget_amount', 'currency']);
        });
    }
};
