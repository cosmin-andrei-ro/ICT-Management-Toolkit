<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Power (ADR 0049): feeds, UPSs and PDUs in a chain, each on side A or B,
 * and the equipment plugged into them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dcim_power_sources', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            $table->uuid('location_id');
            // feed (utility or generator), ups, pdu.
            $table->string('kind', 8);
            $table->string('name', 64);
            $table->char('side', 1)->nullable();
            // What feeds it: a PDU from a UPS, a UPS from a feed.
            $table->uuid('upstream_id')->nullable();
            // The UPS or PDU in the asset register, through Core's PhysicalAssets.
            $table->uuid('asset_id')->nullable();
            // A PDU's rack.
            $table->uuid('rack_id')->nullable();
            $table->unsignedInteger('capacity_watts')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(['organization_id', 'asset_id']);
            $table->index('upstream_id');
            $table->index('rack_id');
        });
        DB::statement('ALTER TABLE dcim_power_sources ADD CONSTRAINT dcim_power_sources_location_fk FOREIGN KEY (organization_id, location_id) REFERENCES core_locations (organization_id, id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE dcim_power_sources ADD CONSTRAINT dcim_power_sources_upstream_fk FOREIGN KEY (organization_id, upstream_id) REFERENCES dcim_power_sources (organization_id, id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE dcim_power_sources ADD CONSTRAINT dcim_power_sources_rack_fk FOREIGN KEY (organization_id, rack_id) REFERENCES dcim_racks (organization_id, id) ON DELETE SET NULL (rack_id)');
        DB::statement("ALTER TABLE dcim_power_sources ADD CONSTRAINT dcim_power_sources_kind_check CHECK (kind IN ('feed', 'ups', 'pdu'))");
        DB::statement("ALTER TABLE dcim_power_sources ADD CONSTRAINT dcim_power_sources_side_check CHECK (side IS NULL OR side IN ('a', 'b'))");
        DB::statement("ALTER TABLE dcim_power_sources ADD CONSTRAINT dcim_power_sources_chain_check CHECK ((kind = 'feed' AND upstream_id IS NULL) OR kind <> 'feed')");
        DB::statement('ALTER TABLE dcim_power_sources ADD CONSTRAINT dcim_power_sources_not_own_upstream CHECK (upstream_id IS NULL OR upstream_id <> id)');
        DB::statement('CREATE UNIQUE INDEX dcim_power_sources_name_unique ON dcim_power_sources (organization_id, lower(name))');

        Schema::create('dcim_power_connections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('mount_id');
            $table->uuid('source_id');
            $table->string('outlet', 32)->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['mount_id', 'source_id']);
            $table->index('source_id');
        });
        DB::statement('ALTER TABLE dcim_power_connections ADD CONSTRAINT dcim_power_connections_mount_fk FOREIGN KEY (organization_id, mount_id) REFERENCES dcim_mounts (organization_id, id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE dcim_power_connections ADD CONSTRAINT dcim_power_connections_source_fk FOREIGN KEY (organization_id, source_id) REFERENCES dcim_power_sources (organization_id, id) ON DELETE RESTRICT');
        DB::statement('CREATE UNIQUE INDEX dcim_power_connections_outlet_unique ON dcim_power_connections (source_id, lower(outlet)) WHERE outlet IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('dcim_power_connections');
        Schema::dropIfExists('dcim_power_sources');
    }
};
