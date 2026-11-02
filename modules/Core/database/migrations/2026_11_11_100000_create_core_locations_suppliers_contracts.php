<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shared records (ADR 0019): locations, suppliers with their contacts, and
 * contracts with renewal reminders. Asset management, DCIM, BIA and the
 * application portfolio refer to them instead of keeping their own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_locations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            $table->uuid('parent_id')->nullable();
            $table->string('name');
            $table->string('kind', 16)->default('site');
            $table->string('code', 32)->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['organization_id', 'id']);
            $table->index('parent_id');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE core_locations
                ADD CONSTRAINT core_locations_kind_check CHECK (kind IN ('site', 'building', 'floor', 'room', 'other')),
                ADD CONSTRAINT core_locations_not_own_parent CHECK (parent_id IS NULL OR parent_id <> id),
                ADD CONSTRAINT core_locations_parent_fk FOREIGN KEY (organization_id, parent_id)
                    REFERENCES core_locations (organization_id, id) ON DELETE RESTRICT
            SQL);
        // Two rooms may share a name in different buildings, not in the same one.
        DB::statement('CREATE UNIQUE INDEX core_locations_name_unique ON core_locations (organization_id, parent_id, lower(name)) NULLS NOT DISTINCT WHERE deleted_at IS NULL');

        Schema::create('core_suppliers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            $table->string('name');
            $table->string('legal_name')->nullable();
            // Fiscal code (CUI), VAT number or another registration number.
            $table->string('tax_id', 64)->nullable();
            // Our customer number at the supplier, asked for when calling support.
            $table->string('customer_number', 64)->nullable();
            $table->string('website')->nullable();
            $table->string('support_email')->nullable();
            $table->string('support_phone', 64)->nullable();
            $table->string('support_hours')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['organization_id', 'id']);
        });
        DB::statement('CREATE UNIQUE INDEX core_suppliers_name_unique ON core_suppliers (organization_id, lower(name)) WHERE deleted_at IS NULL');

        // Business contact details only (GDPR: data minimisation).
        Schema::create('core_supplier_contacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->cascadeOnDelete();
            $table->uuid('supplier_id');
            $table->string('name');
            $table->string('role')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index('supplier_id');
        });
        DB::statement(<<<'SQL'
            ALTER TABLE core_supplier_contacts
                ADD CONSTRAINT core_supplier_contacts_supplier_fk FOREIGN KEY (organization_id, supplier_id)
                    REFERENCES core_suppliers (organization_id, id) ON DELETE CASCADE
            SQL);

        Schema::create('core_contracts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            $table->uuid('supplier_id');
            $table->string('title');
            // The supplier's or our own contract number.
            $table->string('reference', 100)->nullable();
            $table->string('type', 16);
            $table->date('start_date');
            // Null: no fixed end.
            $table->date('end_date')->nullable();
            $table->boolean('auto_renews')->default(false);
            $table->unsignedSmallInteger('renewal_months')->nullable();
            $table->unsignedSmallInteger('notice_days')->nullable();
            $table->date('terminated_on')->nullable();
            $table->decimal('value', 14, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('billing_period', 16)->nullable();
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['organization_id', 'id']);
            $table->index(['organization_id', 'end_date']);
            $table->index('supplier_id');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE core_contracts
                ADD CONSTRAINT core_contracts_type_check CHECK (type IN ('support', 'license', 'subscription', 'lease', 'purchase', 'service', 'other')),
                ADD CONSTRAINT core_contracts_billing_check CHECK (billing_period IS NULL OR billing_period IN ('one_time', 'monthly', 'quarterly', 'yearly')),
                ADD CONSTRAINT core_contracts_period_check CHECK (end_date IS NULL OR end_date >= start_date),
                ADD CONSTRAINT core_contracts_terminated_check CHECK (terminated_on IS NULL OR terminated_on >= start_date),
                -- Renewing needs an end to renew from and a term to renew for.
                ADD CONSTRAINT core_contracts_renewal_check CHECK (NOT auto_renews OR (end_date IS NOT NULL AND renewal_months BETWEEN 1 AND 120)),
                ADD CONSTRAINT core_contracts_value_check CHECK (value IS NULL OR (value >= 0 AND currency IS NOT NULL)),
                ADD CONSTRAINT core_contracts_currency_check CHECK (currency IS NULL OR currency ~ '^[A-Z]{3}$'),
                ADD CONSTRAINT core_contracts_supplier_fk FOREIGN KEY (organization_id, supplier_id)
                    REFERENCES core_suppliers (organization_id, id) ON DELETE RESTRICT
            SQL);

        // One row per reminder sent, so the daily job never sends the same one twice.
        Schema::create('core_contract_reminders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('contract_id')->constrained('core_contracts')->cascadeOnDelete();
            // The date acted on: end date minus notice period. A new end date means new reminders.
            $table->date('deadline');
            $table->unsignedSmallInteger('days_before');
            $table->timestampTz('sent_at');

            $table->unique(['contract_id', 'deadline', 'days_before']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_contract_reminders');
        Schema::dropIfExists('core_contracts');
        Schema::dropIfExists('core_supplier_contacts');
        Schema::dropIfExists('core_suppliers');
        Schema::dropIfExists('core_locations');
    }
};
