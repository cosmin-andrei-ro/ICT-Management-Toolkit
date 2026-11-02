<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The risk register (ADR 0031): the organization's scoring method, a catalog
 * of threats and vulnerabilities, and risk scenarios linked to what they
 * concern. Applications, CMDB items and business processes belong to other
 * modules and are referenced by id through Core contracts, without foreign keys.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_settings', function (Blueprint $table): void {
            $table->foreignUuid('organization_id')->primary()->constrained('core_organizations')->cascadeOnDelete();
            // Five levels each: [{label, description}].
            $table->jsonb('likelihood_levels');
            $table->jsonb('impact_levels');
            // Lowest score (likelihood × impact) of each band.
            $table->unsignedSmallInteger('medium_from');
            $table->unsignedSmallInteger('high_from');
            $table->unsignedSmallInteger('critical_from');
            // From this score, accepting a risk needs a risk manager's approval.
            $table->unsignedSmallInteger('approval_from');
            $table->unsignedSmallInteger('review_months');
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });
        DB::statement(<<<'SQL'
            ALTER TABLE risk_settings
                ADD CONSTRAINT risk_settings_bands_check CHECK (1 < medium_from AND medium_from < high_from AND high_from < critical_from AND critical_from <= 25),
                ADD CONSTRAINT risk_settings_approval_check CHECK (approval_from BETWEEN 1 AND 25),
                ADD CONSTRAINT risk_settings_review_check CHECK (review_months BETWEEN 1 AND 60)
            SQL);

        Schema::create('risk_catalog_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('name');
            $table->string('description', 2000)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
        });
        DB::statement("ALTER TABLE risk_catalog_entries ADD CONSTRAINT risk_catalog_entries_kind_check CHECK (kind IN ('threat', 'vulnerability'))");
        DB::statement('CREATE UNIQUE INDEX risk_catalog_entries_name_unique ON risk_catalog_entries (organization_id, kind, lower(name))');

        Schema::create('risk_risks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            // R-0001, per organization.
            $table->unsignedInteger('reference');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 32);
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('open');
            $table->unsignedSmallInteger('inherent_likelihood')->nullable();
            $table->unsignedSmallInteger('inherent_impact')->nullable();
            $table->text('existing_controls')->nullable();
            $table->date('next_review_on')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(['organization_id', 'reference']);
            $table->index('owner_id');
        });
        DB::statement(<<<'SQL'
            ALTER TABLE risk_risks
                ADD CONSTRAINT risk_risks_category_check CHECK (category IN ('information_security', 'availability', 'compliance', 'supplier', 'operational', 'project')),
                ADD CONSTRAINT risk_risks_status_check CHECK (status IN ('open', 'closed')),
                ADD CONSTRAINT risk_risks_likelihood_check CHECK (inherent_likelihood BETWEEN 1 AND 5),
                ADD CONSTRAINT risk_risks_impact_check CHECK (inherent_impact BETWEEN 1 AND 5),
                ADD CONSTRAINT risk_risks_scored_check CHECK ((inherent_likelihood IS NULL) = (inherent_impact IS NULL))
            SQL);

        Schema::create('risk_risk_catalog', function (Blueprint $table): void {
            $table->uuid('risk_id');
            $table->uuid('organization_id');
            $table->uuid('entry_id');

            $table->primary(['risk_id', 'entry_id']);
        });
        DB::statement(<<<'SQL'
            ALTER TABLE risk_risk_catalog
                ADD CONSTRAINT risk_risk_catalog_risk_fk FOREIGN KEY (organization_id, risk_id) REFERENCES risk_risks (organization_id, id) ON DELETE CASCADE,
                ADD CONSTRAINT risk_risk_catalog_entry_fk FOREIGN KEY (organization_id, entry_id) REFERENCES risk_catalog_entries (organization_id, id) ON DELETE RESTRICT
            SQL);

        // What a risk concerns, by kind (through Core contracts or Core's suppliers).
        Schema::create('risk_risk_assets', function (Blueprint $table): void {
            $table->uuid('risk_id');
            $table->uuid('organization_id');
            $table->string('asset_type', 32);
            $table->uuid('asset_id');

            $table->primary(['risk_id', 'asset_type', 'asset_id']);
            $table->index(['asset_type', 'asset_id']);
        });
        DB::statement(<<<'SQL'
            ALTER TABLE risk_risk_assets
                ADD CONSTRAINT risk_risk_assets_type_check CHECK (asset_type IN ('application', 'configuration_item', 'process', 'supplier')),
                ADD CONSTRAINT risk_risk_assets_risk_fk FOREIGN KEY (organization_id, risk_id) REFERENCES risk_risks (organization_id, id) ON DELETE CASCADE
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_risk_assets');
        Schema::dropIfExists('risk_risk_catalog');
        Schema::dropIfExists('risk_risks');
        Schema::dropIfExists('risk_catalog_entries');
        Schema::dropIfExists('risk_settings');
    }
};
