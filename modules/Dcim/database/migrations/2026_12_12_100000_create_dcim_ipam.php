<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * IP address management (ADR 0050): VLANs, subnets as PostgreSQL cidr (their
 * hierarchy follows from containment), and addresses as inet - one record
 * per address per organization, refused twice by the database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dcim_vlans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            $table->unsignedSmallInteger('vid');
            $table->string('name', 64);
            // The site or room it belongs to; null for one used everywhere.
            $table->uuid('location_id')->nullable();
            $table->string('description', 255)->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
        });
        DB::statement('ALTER TABLE dcim_vlans ADD CONSTRAINT dcim_vlans_location_fk FOREIGN KEY (organization_id, location_id) REFERENCES core_locations (organization_id, id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE dcim_vlans ADD CONSTRAINT dcim_vlans_vid_check CHECK (vid BETWEEN 1 AND 4094)');
        DB::statement('CREATE UNIQUE INDEX dcim_vlans_vid_unique ON dcim_vlans (organization_id, location_id, vid) NULLS NOT DISTINCT');

        Schema::create('dcim_subnets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            $table->uuid('vlan_id')->nullable();
            $table->uuid('location_id')->nullable();
            $table->string('purpose', 255)->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
        });
        DB::statement('ALTER TABLE dcim_subnets ADD COLUMN network cidr NOT NULL');
        DB::statement('ALTER TABLE dcim_subnets ADD COLUMN gateway inet');
        DB::statement('ALTER TABLE dcim_subnets ADD CONSTRAINT dcim_subnets_gateway_inside CHECK (gateway IS NULL OR (gateway << network AND masklen(gateway) = (CASE WHEN family(gateway) = 4 THEN 32 ELSE 128 END)))');
        DB::statement('ALTER TABLE dcim_subnets ADD CONSTRAINT dcim_subnets_vlan_fk FOREIGN KEY (organization_id, vlan_id) REFERENCES dcim_vlans (organization_id, id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE dcim_subnets ADD CONSTRAINT dcim_subnets_location_fk FOREIGN KEY (organization_id, location_id) REFERENCES core_locations (organization_id, id) ON DELETE RESTRICT');
        DB::statement('CREATE UNIQUE INDEX dcim_subnets_network_unique ON dcim_subnets (organization_id, network)');
        DB::statement('CREATE INDEX dcim_subnets_network_gist ON dcim_subnets USING gist (network inet_ops)');

        Schema::create('dcim_ip_addresses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            $table->string('status', 16)->default('active');
            $table->string('dns_name', 253)->nullable();
            // Equipment in a rack, or a CMDB item (through Core's ConfigurationItems, no cross-module key).
            $table->uuid('mount_id')->nullable();
            $table->uuid('configuration_item_id')->nullable();
            $table->string('description', 255)->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index('mount_id');
            $table->index('configuration_item_id');
        });
        DB::statement('ALTER TABLE dcim_ip_addresses ADD COLUMN address inet NOT NULL');
        // A single host, never a range.
        DB::statement('ALTER TABLE dcim_ip_addresses ADD CONSTRAINT dcim_ip_addresses_host CHECK (masklen(address) = (CASE WHEN family(address) = 4 THEN 32 ELSE 128 END))');
        DB::statement("ALTER TABLE dcim_ip_addresses ADD CONSTRAINT dcim_ip_addresses_status_check CHECK (status IN ('active', 'reserved', 'dhcp'))");
        DB::statement('ALTER TABLE dcim_ip_addresses ADD CONSTRAINT dcim_ip_addresses_one_holder CHECK (mount_id IS NULL OR configuration_item_id IS NULL)');
        DB::statement('ALTER TABLE dcim_ip_addresses ADD CONSTRAINT dcim_ip_addresses_mount_fk FOREIGN KEY (organization_id, mount_id) REFERENCES dcim_mounts (organization_id, id) ON DELETE SET NULL (mount_id)');
        DB::statement('CREATE UNIQUE INDEX dcim_ip_addresses_unique ON dcim_ip_addresses (organization_id, address)');
        DB::statement('CREATE INDEX dcim_ip_addresses_gist ON dcim_ip_addresses USING gist (address inet_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('dcim_ip_addresses');
        Schema::dropIfExists('dcim_subnets');
        Schema::dropIfExists('dcim_vlans');
    }
};
