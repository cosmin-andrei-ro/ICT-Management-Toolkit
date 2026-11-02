<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Software licences (ADR 0022): products, the licences bought for them, and who
 * or which device uses each seat. Licence keys are encrypted by the model and
 * shown only on request, after a second-factor code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('itam_software_products', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            $table->string('name');
            $table->string('publisher')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['organization_id', 'id']);
        });
        DB::statement('CREATE UNIQUE INDEX itam_software_products_name_unique ON itam_software_products (organization_id, lower(name)) WHERE deleted_at IS NULL');

        Schema::create('itam_licenses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            $table->uuid('product_id');
            $table->string('name');
            // per_user, per_device, or site (no count).
            $table->string('model', 16);
            $table->unsignedInteger('quantity')->nullable();
            // Encrypted by the model; never logged, audited or exported.
            $table->text('license_key')->nullable();
            $table->uuid('supplier_id')->nullable();
            $table->uuid('contract_id')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('cost', 14, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->date('starts_on')->nullable();
            // Null: perpetual.
            $table->date('expires_on')->nullable();
            // The expiry date a reminder was sent for; a new date means a new reminder.
            $table->date('expiry_reminded_for')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['organization_id', 'id']);
            $table->index(['organization_id', 'product_id']);
            $table->index(['organization_id', 'expires_on']);
            $table->index('contract_id');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE itam_licenses
                ADD CONSTRAINT itam_licenses_model_check CHECK (model IN ('per_user', 'per_device', 'site')),
                -- Counted licences have a number of seats; a site licence has none.
                ADD CONSTRAINT itam_licenses_quantity_check CHECK ((model = 'site') = (quantity IS NULL) AND (quantity IS NULL OR quantity >= 1)),
                ADD CONSTRAINT itam_licenses_period_check CHECK (expires_on IS NULL OR starts_on IS NULL OR expires_on >= starts_on),
                ADD CONSTRAINT itam_licenses_cost_check CHECK (cost IS NULL OR (cost >= 0 AND currency IS NOT NULL)),
                ADD CONSTRAINT itam_licenses_currency_check CHECK (currency IS NULL OR currency ~ '^[A-Z]{3}$'),
                ADD CONSTRAINT itam_licenses_product_fk FOREIGN KEY (organization_id, product_id)
                    REFERENCES itam_software_products (organization_id, id) ON DELETE RESTRICT,
                ADD CONSTRAINT itam_licenses_supplier_fk FOREIGN KEY (organization_id, supplier_id)
                    REFERENCES core_suppliers (organization_id, id) ON DELETE SET NULL (supplier_id),
                ADD CONSTRAINT itam_licenses_contract_fk FOREIGN KEY (organization_id, contract_id)
                    REFERENCES core_contracts (organization_id, id) ON DELETE SET NULL (contract_id)
            SQL);

        // A seat: a person (per-user and site licences) or a device (per-device licences).
        Schema::create('itam_license_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->cascadeOnDelete();
            $table->uuid('license_id');
            $table->foreignUuid('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->uuid('asset_id')->nullable();
            $table->timestampTz('assigned_at');
            $table->foreignUuid('assigned_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index('license_id');
            $table->index('user_id');
            $table->index('asset_id');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE itam_license_assignments
                ADD CONSTRAINT itam_license_assignments_one_holder CHECK ((user_id IS NULL) <> (asset_id IS NULL)),
                ADD CONSTRAINT itam_license_assignments_license_fk FOREIGN KEY (organization_id, license_id)
                    REFERENCES itam_licenses (organization_id, id) ON DELETE CASCADE,
                ADD CONSTRAINT itam_license_assignments_asset_fk FOREIGN KEY (organization_id, asset_id)
                    REFERENCES itam_assets (organization_id, id) ON DELETE CASCADE
            SQL);
        DB::statement('CREATE UNIQUE INDEX itam_license_assignments_user_unique ON itam_license_assignments (license_id, user_id) WHERE user_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX itam_license_assignments_asset_unique ON itam_license_assignments (license_id, asset_id) WHERE asset_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('itam_license_assignments');
        Schema::dropIfExists('itam_licenses');
        Schema::dropIfExists('itam_software_products');
    }
};
