<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * IT asset management (ADR 0020): hardware assets of extensible categories, their
 * life cycle from order to disposal, and who had them when.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('itam_categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            $table->string('key', 64);
            $table->string('name');
            $table->text('description')->nullable();
            // Core FormSchema: the attributes assets of this category carry.
            $table->jsonb('attribute_schema')->default('[]');
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['organization_id', 'id']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE itam_categories
                ADD CONSTRAINT itam_categories_key_format CHECK (key ~ '^[a-z][a-z0-9_]*$'),
                ADD CONSTRAINT itam_categories_schema_list CHECK (jsonb_typeof(attribute_schema) = 'array')
            SQL);
        DB::statement('CREATE UNIQUE INDEX itam_categories_key_unique ON itam_categories (organization_id, key) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX itam_categories_name_unique ON itam_categories (organization_id, lower(name)) WHERE deleted_at IS NULL');

        // Last asset tag number handed out per organization (AST-000123).
        Schema::create('itam_tag_sequences', function (Blueprint $table): void {
            $table->foreignUuid('organization_id')->primary()->constrained('core_organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('last_value');
        });

        Schema::create('itam_assets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            $table->uuid('category_id');
            $table->string('asset_tag', 64);
            $table->string('name')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('status', 16)->default('in_stock');
            $table->uuid('location_id')->nullable();
            // The person who has it now; the history is in itam_asset_assignments.
            $table->foreignUuid('assigned_to_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->uuid('supplier_id')->nullable();
            // Support or warranty contract.
            $table->uuid('contract_id')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 14, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('order_number', 100)->nullable();
            $table->string('invoice_number', 100)->nullable();
            $table->date('warranty_ends_on')->nullable();
            // The warranty end date a reminder was sent for; a new date means a new reminder.
            $table->date('warranty_reminded_for')->nullable();
            $table->date('replace_on')->nullable();
            $table->jsonb('properties')->default('{}');
            $table->text('notes')->nullable();
            $table->date('disposed_on')->nullable();
            $table->string('disposal_method', 16)->nullable();
            // Null: the asset stores no data (a monitor); otherwise whether it was wiped.
            $table->boolean('data_wiped')->nullable();
            $table->text('disposal_notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['organization_id', 'id']);
            $table->index(['organization_id', 'category_id']);
            $table->index(['organization_id', 'status']);
            $table->index('assigned_to_id');
            $table->index(['organization_id', 'warranty_ends_on']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE itam_assets
                ADD CONSTRAINT itam_assets_status_check CHECK (status IN ('ordered', 'in_stock', 'deployed', 'in_repair', 'retired', 'lost', 'disposed')),
                ADD CONSTRAINT itam_assets_disposal_method_check CHECK (disposal_method IS NULL OR disposal_method IN ('sold', 'donated', 'recycled', 'destroyed', 'returned')),
                -- Only a deployed asset, or one away for repair, can be with someone.
                ADD CONSTRAINT itam_assets_holder_check CHECK (assigned_to_id IS NULL OR status IN ('deployed', 'in_repair')),
                ADD CONSTRAINT itam_assets_disposal_check CHECK ((status = 'disposed') = (disposed_on IS NOT NULL AND disposal_method IS NOT NULL)),
                ADD CONSTRAINT itam_assets_cost_check CHECK (purchase_cost IS NULL OR (purchase_cost >= 0 AND currency IS NOT NULL)),
                ADD CONSTRAINT itam_assets_currency_check CHECK (currency IS NULL OR currency ~ '^[A-Z]{3}$'),
                ADD CONSTRAINT itam_assets_properties_object CHECK (jsonb_typeof(properties) = 'object'),
                ADD CONSTRAINT itam_assets_category_fk FOREIGN KEY (organization_id, category_id)
                    REFERENCES itam_categories (organization_id, id) ON DELETE RESTRICT,
                ADD CONSTRAINT itam_assets_location_fk FOREIGN KEY (organization_id, location_id)
                    REFERENCES core_locations (organization_id, id) ON DELETE SET NULL (location_id),
                ADD CONSTRAINT itam_assets_supplier_fk FOREIGN KEY (organization_id, supplier_id)
                    REFERENCES core_suppliers (organization_id, id) ON DELETE SET NULL (supplier_id),
                ADD CONSTRAINT itam_assets_contract_fk FOREIGN KEY (organization_id, contract_id)
                    REFERENCES core_contracts (organization_id, id) ON DELETE SET NULL (contract_id)
            SQL);
        DB::statement('CREATE UNIQUE INDEX itam_assets_tag_unique ON itam_assets (organization_id, lower(asset_tag)) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX itam_assets_serial_unique ON itam_assets (organization_id, lower(serial_number)) WHERE serial_number IS NOT NULL AND deleted_at IS NULL');
        DB::statement('CREATE INDEX itam_assets_contract_index ON itam_assets (contract_id) WHERE contract_id IS NOT NULL');
        DB::statement(<<<'SQL'
            ALTER TABLE itam_assets ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (
                setweight(to_tsvector('simple', asset_tag), 'A')
                || setweight(to_tsvector('simple', coalesce(serial_number, '')), 'A')
                || setweight(to_tsvector('simple', coalesce(name, '')), 'A')
                || setweight(to_tsvector('simple', coalesce(manufacturer, '') || ' ' || coalesce(model, '')), 'B')
                || setweight(jsonb_to_tsvector('simple', properties, '["string"]'), 'C')
            ) STORED
            SQL);
        DB::statement('CREATE INDEX itam_assets_search_vector_index ON itam_assets USING GIN (search_vector)');

        Schema::create('itam_asset_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->cascadeOnDelete();
            $table->uuid('asset_id');
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('assigned_at');
            $table->foreignUuid('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 1000)->nullable();
            $table->timestampTz('returned_at')->nullable();
            $table->foreignUuid('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('return_notes', 1000)->nullable();

            $table->index('asset_id');
            $table->index('user_id');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE itam_asset_assignments
                ADD CONSTRAINT itam_asset_assignments_period_check CHECK (returned_at IS NULL OR returned_at >= assigned_at),
                ADD CONSTRAINT itam_asset_assignments_asset_fk FOREIGN KEY (organization_id, asset_id)
                    REFERENCES itam_assets (organization_id, id) ON DELETE CASCADE
            SQL);
        // One person at a time: at most one open assignment per asset.
        DB::statement('CREATE UNIQUE INDEX itam_asset_assignments_open_unique ON itam_asset_assignments (asset_id) WHERE returned_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('itam_asset_assignments');
        Schema::dropIfExists('itam_assets');
        Schema::dropIfExists('itam_tag_sequences');
        Schema::dropIfExists('itam_categories');
    }
};
