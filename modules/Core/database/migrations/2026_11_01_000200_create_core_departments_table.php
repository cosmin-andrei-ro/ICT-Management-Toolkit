<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_departments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            $table->uuid('parent_id')->nullable();
            $table->string('name');
            $table->string('code', 32)->nullable();
            $table->text('description')->nullable();
            $table->foreignUuid('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            // Target for the composite parent foreign key below.
            $table->unique(['organization_id', 'id']);
            $table->index('parent_id');
        });

        // The composite key makes it impossible for a department to have a parent in
        // another organization. SET NULL (parent_id) leaves organization_id untouched.
        DB::statement(<<<'SQL'
            ALTER TABLE core_departments
                ADD CONSTRAINT core_departments_parent_fk
                FOREIGN KEY (organization_id, parent_id)
                REFERENCES core_departments (organization_id, id)
                ON DELETE SET NULL (parent_id)
            SQL);

        DB::statement('ALTER TABLE core_departments ADD CONSTRAINT core_departments_not_own_parent CHECK (parent_id IS NULL OR parent_id <> id)');

        DB::statement('CREATE UNIQUE INDEX core_departments_org_name_unique ON core_departments (organization_id, lower(name)) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('core_departments');
    }
};
