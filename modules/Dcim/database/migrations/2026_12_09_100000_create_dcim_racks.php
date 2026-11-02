<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Racks in rooms and the assets mounted in them (ADR 0047). Two mounts never
 * share a unit on the same side of a rack: the database refuses it.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Equality on uuid inside a GiST exclusion constraint; a trusted extension.
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        Schema::create('dcim_racks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            // A room (Core location); the rack's position in it.
            $table->uuid('location_id');
            $table->string('name', 64);
            $table->string('row', 32)->nullable();
            $table->unsignedSmallInteger('position')->nullable();
            $table->unsignedSmallInteger('height_u')->default(42);
            $table->unsignedInteger('max_power_watts')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['organization_id', 'id']);
            $table->index('location_id');
        });
        DB::statement('ALTER TABLE dcim_racks ADD CONSTRAINT dcim_racks_location_fk FOREIGN KEY (organization_id, location_id) REFERENCES core_locations (organization_id, id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE dcim_racks ADD CONSTRAINT dcim_racks_height_check CHECK (height_u BETWEEN 1 AND 60)');
        DB::statement('CREATE UNIQUE INDEX dcim_racks_name_unique ON dcim_racks (location_id, lower(name)) WHERE deleted_at IS NULL');

        Schema::create('dcim_mounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('rack_id');
            // An asset of the asset register, through Core's PhysicalAssets (no cross-module key).
            $table->uuid('asset_id');
            // Lowest unit occupied; null for zero-U equipment on the rack's side (vertical PDUs).
            $table->unsignedSmallInteger('position_u')->nullable();
            $table->unsignedSmallInteger('height_u')->default(1);
            $table->string('face', 8)->default('front');
            // Full depth occupies both sides of its units.
            $table->boolean('full_depth')->default(true);
            $table->unsignedInteger('power_watts')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['organization_id', 'asset_id']);
            $table->index('rack_id');
        });
        DB::statement('ALTER TABLE dcim_mounts ADD CONSTRAINT dcim_mounts_rack_fk FOREIGN KEY (organization_id, rack_id) REFERENCES dcim_racks (organization_id, id) ON DELETE CASCADE');
        DB::statement("ALTER TABLE dcim_mounts ADD CONSTRAINT dcim_mounts_face_check CHECK (face IN ('front', 'rear'))");
        DB::statement('ALTER TABLE dcim_mounts ADD CONSTRAINT dcim_mounts_units_check CHECK (height_u BETWEEN 1 AND 60 AND (position_u IS NULL OR position_u >= 1))');
        // The units taken on each side, so the exclusion constraints can compare them.
        DB::statement("ALTER TABLE dcim_mounts ADD COLUMN front_units int4range GENERATED ALWAYS AS (CASE WHEN position_u IS NOT NULL AND (face = 'front' OR full_depth) THEN int4range(position_u, position_u + height_u) END) STORED");
        DB::statement("ALTER TABLE dcim_mounts ADD COLUMN rear_units int4range GENERATED ALWAYS AS (CASE WHEN position_u IS NOT NULL AND (face = 'rear' OR full_depth) THEN int4range(position_u, position_u + height_u) END) STORED");
        DB::statement('ALTER TABLE dcim_mounts ADD CONSTRAINT dcim_mounts_front_free EXCLUDE USING gist (rack_id WITH =, front_units WITH &&) WHERE (front_units IS NOT NULL)');
        DB::statement('ALTER TABLE dcim_mounts ADD CONSTRAINT dcim_mounts_rear_free EXCLUDE USING gist (rack_id WITH =, rear_units WITH &&) WHERE (rear_units IS NOT NULL)');

        // A mount stays inside its rack, and a rack is never made shorter than what it holds.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION dcim_mounts_within_rack() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.position_u IS NOT NULL AND NEW.position_u + NEW.height_u - 1 > (SELECT height_u FROM dcim_racks WHERE id = NEW.rack_id) THEN
                    RAISE EXCEPTION 'Mount % goes beyond the top of its rack', NEW.id USING ERRCODE = 'check_violation';
                END IF;
                RETURN NEW;
            END $$;
            CREATE TRIGGER dcim_mounts_within_rack BEFORE INSERT OR UPDATE ON dcim_mounts FOR EACH ROW EXECUTE FUNCTION dcim_mounts_within_rack();

            CREATE OR REPLACE FUNCTION dcim_racks_hold_mounts() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF EXISTS (SELECT 1 FROM dcim_mounts WHERE rack_id = NEW.id AND position_u IS NOT NULL AND position_u + height_u - 1 > NEW.height_u) THEN
                    RAISE EXCEPTION 'Rack % is shorter than the equipment it holds', NEW.id USING ERRCODE = 'check_violation';
                END IF;
                RETURN NEW;
            END $$;
            CREATE TRIGGER dcim_racks_hold_mounts BEFORE UPDATE OF height_u ON dcim_racks FOR EACH ROW EXECUTE FUNCTION dcim_racks_hold_mounts();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('dcim_mounts');
        Schema::dropIfExists('dcim_racks');
        DB::unprepared('DROP FUNCTION IF EXISTS dcim_mounts_within_rack(); DROP FUNCTION IF EXISTS dcim_racks_hold_mounts();');
    }
};
