<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Service catalog: what people can request from the portal, each with its own
 * form, routing and default impact/urgency. Answers live on the ticket.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticketing_services', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->uuid('category_id')->nullable();
            $table->uuid('queue_id')->nullable();
            $table->string('impact', 16)->default('low');
            $table->string('urgency', 16)->default('low');
            $table->jsonb('form_schema')->default('[]');
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['organization_id', 'id']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE ticketing_services
                ADD CONSTRAINT ticketing_services_impact_check CHECK (impact IN ('high', 'medium', 'low')),
                ADD CONSTRAINT ticketing_services_urgency_check CHECK (urgency IN ('high', 'medium', 'low')),
                ADD CONSTRAINT ticketing_services_form_schema_list CHECK (jsonb_typeof(form_schema) = 'array'),
                ADD CONSTRAINT ticketing_services_category_fk FOREIGN KEY (organization_id, category_id)
                    REFERENCES ticketing_categories (organization_id, id) ON DELETE SET NULL (category_id),
                ADD CONSTRAINT ticketing_services_queue_fk FOREIGN KEY (organization_id, queue_id)
                    REFERENCES ticketing_queues (organization_id, id) ON DELETE SET NULL (queue_id)
            SQL);
        DB::statement('CREATE UNIQUE INDEX ticketing_services_name_unique ON ticketing_services (organization_id, lower(name)) WHERE deleted_at IS NULL');

        Schema::table('ticketing_tickets', function (Blueprint $table): void {
            $table->uuid('service_id')->nullable();
            $table->index(['organization_id', 'service_id']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE ticketing_tickets ADD CONSTRAINT ticketing_tickets_service_fk FOREIGN KEY (organization_id, service_id)
                REFERENCES ticketing_services (organization_id, id) ON DELETE SET NULL (service_id)
            SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE ticketing_tickets DROP CONSTRAINT ticketing_tickets_service_fk');

        Schema::table('ticketing_tickets', function (Blueprint $table): void {
            $table->dropIndex(['organization_id', 'service_id']);
            $table->dropColumn('service_id');
        });

        Schema::dropIfExists('ticketing_services');
    }
};
