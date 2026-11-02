<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The application portfolio (ADR 0029): each application and its technical
 * documentation sheet in versions. The sheet's content follows the owner's
 * template chapter by chapter and is kept as validated JSON per version;
 * major versions are approved by the business owner, the IT owner and
 * security, minor ones are published and logged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications_applications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->foreignUuid('department_id')->nullable()->constrained('core_departments')->nullOnDelete();
            $table->string('department_email')->nullable();
            $table->foreignUuid('business_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('technical_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('deputy_id')->nullable()->constrained('users')->nullOnDelete();
            // Copied from the sheet in force, for lists and filters.
            $table->string('status')->nullable();
            $table->string('criticality')->nullable();
            $table->string('current_version', 16)->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->date('next_review_on')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['organization_id', 'id']);
            $table->index('business_owner_id');
            $table->index('technical_owner_id');
            $table->index('deputy_id');
        });
        DB::statement('CREATE UNIQUE INDEX applications_applications_code_unique ON applications_applications (organization_id, upper(code)) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX applications_applications_name_unique ON applications_applications (organization_id, lower(name)) WHERE deleted_at IS NULL');

        Schema::create('applications_sheets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->cascadeOnDelete();
            $table->uuid('application_id');
            $table->string('status', 16)->default('draft');
            // Needs the three approvals (always for the first version).
            $table->boolean('is_major')->default(false);
            // Numbered when the version comes into force (1.0, 1.1, 2.0…).
            $table->unsignedInteger('major')->nullable();
            $table->unsignedInteger('minor')->nullable();
            $table->text('change_summary')->nullable();
            $table->jsonb('content')->default('{}');
            $table->timestampTz('submitted_at')->nullable();
            $table->foreignUuid('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('returned_at')->nullable();
            $table->foreignUuid('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('return_comment')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->index('application_id');
        });
        DB::statement(<<<'SQL'
            ALTER TABLE applications_sheets
                ADD CONSTRAINT applications_sheets_status_check CHECK (status IN ('draft', 'submitted', 'returned', 'approved', 'superseded')),
                ADD CONSTRAINT applications_sheets_content_check CHECK (jsonb_typeof(content) = 'object'),
                ADD CONSTRAINT applications_sheets_number_check CHECK ((status IN ('approved', 'superseded')) = (major IS NOT NULL AND minor IS NOT NULL AND approved_at IS NOT NULL)),
                ADD CONSTRAINT applications_sheets_application_fk FOREIGN KEY (organization_id, application_id)
                    REFERENCES applications_applications (organization_id, id) ON DELETE CASCADE
            SQL);
        DB::statement("CREATE UNIQUE INDEX applications_sheets_open_unique ON applications_sheets (application_id) WHERE status IN ('draft', 'submitted', 'returned')");
        DB::statement("CREATE UNIQUE INDEX applications_sheets_current_unique ON applications_sheets (application_id) WHERE status = 'approved'");
        DB::statement('CREATE UNIQUE INDEX applications_sheets_number_unique ON applications_sheets (application_id, major, minor) WHERE major IS NOT NULL');

        // One approval per role on a submitted version.
        Schema::create('applications_sheet_approvals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('sheet_id');
            $table->string('role', 16);
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('comment', 2000)->nullable();
            $table->timestampTz('approved_at');

            $table->unique(['sheet_id', 'role']);
        });
        DB::statement(<<<'SQL'
            ALTER TABLE applications_sheet_approvals
                ADD CONSTRAINT applications_sheet_approvals_role_check CHECK (role IN ('business', 'technical', 'security')),
                ADD CONSTRAINT applications_sheet_approvals_sheet_fk FOREIGN KEY (organization_id, sheet_id)
                    REFERENCES applications_sheets (organization_id, id) ON DELETE CASCADE
            SQL);

        // The values of the sheet's dropdowns, editable per organization.
        Schema::create('applications_option_values', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->cascadeOnDelete();
            $table->string('list', 64);
            $table->string('key', 64);
            $table->string('label');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['organization_id', 'list', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications_option_values');
        Schema::dropIfExists('applications_sheet_approvals');
        Schema::dropIfExists('applications_sheets');
        Schema::dropIfExists('applications_applications');
    }
};
