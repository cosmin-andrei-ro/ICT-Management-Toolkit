<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ports of mounted equipment and the cables between them (ADR 0048). A
 * patch panel's front port passes through to one rear port; each port takes
 * at most one cable, which the database guarantees through one row per
 * cable end.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dcim_ports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('mount_id');
            $table->string('name', 64);
            $table->string('kind', 16);
            $table->string('side', 8)->default('front');
            // Front port of a patch panel: the rear port it is wired to.
            $table->uuid('rear_port_id')->nullable()->unique();
            $table->unsignedInteger('position')->default(0);
            $table->string('description', 255)->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->index(['mount_id', 'position']);
        });
        DB::statement('ALTER TABLE dcim_mounts ADD CONSTRAINT dcim_mounts_org_id_unique UNIQUE (organization_id, id)');
        DB::statement('ALTER TABLE dcim_ports ADD CONSTRAINT dcim_ports_mount_fk FOREIGN KEY (organization_id, mount_id) REFERENCES dcim_mounts (organization_id, id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE dcim_ports ADD CONSTRAINT dcim_ports_rear_fk FOREIGN KEY (organization_id, rear_port_id) REFERENCES dcim_ports (organization_id, id) ON DELETE SET NULL (rear_port_id)');
        DB::statement("ALTER TABLE dcim_ports ADD CONSTRAINT dcim_ports_kind_check CHECK (kind IN ('rj45', 'sfp', 'sfp_plus', 'sfp28', 'qsfp', 'lc', 'sc', 'console', 'other'))");
        DB::statement("ALTER TABLE dcim_ports ADD CONSTRAINT dcim_ports_side_check CHECK (side IN ('front', 'rear'))");
        DB::statement('ALTER TABLE dcim_ports ADD CONSTRAINT dcim_ports_not_own_rear CHECK (rear_port_id IS NULL OR rear_port_id <> id)');
        DB::statement('CREATE UNIQUE INDEX dcim_ports_name_unique ON dcim_ports (mount_id, lower(name))');

        Schema::create('dcim_cables', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('label', 64)->nullable();
            $table->string('type', 16);
            $table->string('colour', 32)->nullable();
            $table->decimal('length_m', 7, 2)->nullable();
            $table->string('status', 16)->default('connected');
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->foreign('organization_id')->references('id')->on('core_organizations')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE dcim_cables ADD CONSTRAINT dcim_cables_type_check CHECK (type IN ('cat5e', 'cat6', 'cat6a', 'cat7', 'om3', 'om4', 'os2', 'dac', 'aoc', 'console', 'other'))");
        DB::statement("ALTER TABLE dcim_cables ADD CONSTRAINT dcim_cables_status_check CHECK (status IN ('connected', 'planned'))");
        DB::statement('ALTER TABLE dcim_cables ADD CONSTRAINT dcim_cables_length_check CHECK (length_m IS NULL OR length_m > 0)');
        DB::statement('CREATE UNIQUE INDEX dcim_cables_label_unique ON dcim_cables (organization_id, lower(label)) WHERE label IS NOT NULL');

        Schema::create('dcim_cable_ends', function (Blueprint $table): void {
            $table->uuid('cable_id');
            $table->uuid('organization_id');
            $table->char('end', 1);
            // One cable per port.
            $table->uuid('port_id')->unique();

            $table->primary(['cable_id', 'end']);
        });
        DB::statement('ALTER TABLE dcim_cable_ends ADD CONSTRAINT dcim_cable_ends_cable_fk FOREIGN KEY (organization_id, cable_id) REFERENCES dcim_cables (organization_id, id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE dcim_cable_ends ADD CONSTRAINT dcim_cable_ends_port_fk FOREIGN KEY (organization_id, port_id) REFERENCES dcim_ports (organization_id, id) ON DELETE CASCADE');
        DB::statement("ALTER TABLE dcim_cable_ends ADD CONSTRAINT dcim_cable_ends_end_check CHECK (\"end\" IN ('a', 'b'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('dcim_cable_ends');
        Schema::dropIfExists('dcim_cables');
        Schema::dropIfExists('dcim_ports');
        DB::statement('ALTER TABLE dcim_mounts DROP CONSTRAINT IF EXISTS dcim_mounts_org_id_unique');
    }
};
