<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CMDB (ADR 0018): configuration items of extensible types, typed relationships
 * between them, and links from other records (tickets) to them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cmdb_ci_types', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            // Stable identifier, used by imports: "server", "application"…
            $table->string('key', 64);
            $table->string('name');
            $table->text('description')->nullable();
            // Core FormSchema: the attributes items of this type carry.
            $table->jsonb('attribute_schema')->default('[]');
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['organization_id', 'id']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE cmdb_ci_types
                ADD CONSTRAINT cmdb_ci_types_key_format CHECK (key ~ '^[a-z][a-z0-9_]*$'),
                ADD CONSTRAINT cmdb_ci_types_schema_list CHECK (jsonb_typeof(attribute_schema) = 'array')
            SQL);
        DB::statement('CREATE UNIQUE INDEX cmdb_ci_types_key_unique ON cmdb_ci_types (organization_id, key) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX cmdb_ci_types_name_unique ON cmdb_ci_types (organization_id, lower(name)) WHERE deleted_at IS NULL');

        Schema::create('cmdb_configuration_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            $table->uuid('type_id');
            $table->string('name');
            // Hostname, asset tag or another unique handle; matched by imports.
            $table->string('identifier')->nullable();
            $table->string('status', 16)->default('in_use');
            $table->string('environment', 16)->nullable();
            $table->string('criticality', 16)->default('medium');
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('team_id')->nullable()->constrained('core_teams')->nullOnDelete();
            $table->text('description')->nullable();
            // Values of the type's attributes, keyed by attribute key ("attributes" would clash with Eloquent).
            $table->jsonb('properties')->default('{}');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['organization_id', 'id']);
            $table->index(['organization_id', 'type_id']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE cmdb_configuration_items
                ADD CONSTRAINT cmdb_configuration_items_status_check CHECK (status IN ('planned', 'in_use', 'maintenance', 'retired')),
                ADD CONSTRAINT cmdb_configuration_items_environment_check CHECK (environment IS NULL OR environment IN ('production', 'test', 'development', 'disaster_recovery')),
                ADD CONSTRAINT cmdb_configuration_items_criticality_check CHECK (criticality IN ('low', 'medium', 'high', 'critical')),
                ADD CONSTRAINT cmdb_configuration_items_properties_object CHECK (jsonb_typeof(properties) = 'object'),
                ADD CONSTRAINT cmdb_configuration_items_type_fk FOREIGN KEY (organization_id, type_id)
                    REFERENCES cmdb_ci_types (organization_id, id) ON DELETE RESTRICT
            SQL);
        DB::statement('CREATE UNIQUE INDEX cmdb_configuration_items_identifier_unique ON cmdb_configuration_items (organization_id, lower(identifier)) WHERE identifier IS NOT NULL AND deleted_at IS NULL');
        DB::statement(<<<'SQL'
            ALTER TABLE cmdb_configuration_items ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (
                setweight(to_tsvector('simple', name), 'A')
                || setweight(to_tsvector('simple', coalesce(identifier, '')), 'A')
                || setweight(to_tsvector('english', coalesce(description, '')), 'C')
                || setweight(jsonb_to_tsvector('simple', properties, '["string"]'), 'B')
            ) STORED
            SQL);
        DB::statement('CREATE INDEX cmdb_configuration_items_search_vector_index ON cmdb_configuration_items USING GIN (search_vector)');

        Schema::create('cmdb_relationships', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->cascadeOnDelete();
            $table->uuid('source_id');
            $table->uuid('target_id');
            $table->string('type', 32);
            $table->string('description')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['source_id', 'target_id', 'type']);
            $table->index('target_id');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE cmdb_relationships
                ADD CONSTRAINT cmdb_relationships_type_check CHECK (type IN ('depends_on', 'runs_on', 'uses', 'part_of', 'connects_to')),
                ADD CONSTRAINT cmdb_relationships_not_self CHECK (source_id <> target_id),
                ADD CONSTRAINT cmdb_relationships_source_fk FOREIGN KEY (organization_id, source_id)
                    REFERENCES cmdb_configuration_items (organization_id, id) ON DELETE CASCADE,
                ADD CONSTRAINT cmdb_relationships_target_fk FOREIGN KEY (organization_id, target_id)
                    REFERENCES cmdb_configuration_items (organization_id, id) ON DELETE CASCADE
            SQL);

        // Links from records of other modules (tickets…) to items; polymorphic on the
        // record side by design (CLAUDE.md §5.2: cross-cutting links).
        Schema::create('cmdb_ci_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->cascadeOnDelete();
            $table->uuid('configuration_item_id');
            $table->string('linkable_type', 64);
            $table->uuid('linkable_id');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at');

            $table->unique(['configuration_item_id', 'linkable_type', 'linkable_id']);
            $table->index(['linkable_type', 'linkable_id']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE cmdb_ci_links
                ADD CONSTRAINT cmdb_ci_links_item_fk FOREIGN KEY (organization_id, configuration_item_id)
                    REFERENCES cmdb_configuration_items (organization_id, id) ON DELETE CASCADE
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('cmdb_ci_links');
        Schema::dropIfExists('cmdb_relationships');
        Schema::dropIfExists('cmdb_configuration_items');
        Schema::dropIfExists('cmdb_ci_types');
    }
};
