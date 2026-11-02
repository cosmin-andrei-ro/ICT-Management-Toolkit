<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The BIA coordinator also runs continuity and recovery plans (ADR 0026): the
 * default role is renamed in place, so the people holding it keep it and AD
 * group mappings to it keep working.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('core_roles')->whereNull('organization_id')->where('name', 'bia-coordinator')->update(['name' => 'continuity-coordinator']);
    }

    public function down(): void
    {
        DB::table('core_roles')->whereNull('organization_id')->where('name', 'continuity-coordinator')->update(['name' => 'bia-coordinator']);
    }
};
