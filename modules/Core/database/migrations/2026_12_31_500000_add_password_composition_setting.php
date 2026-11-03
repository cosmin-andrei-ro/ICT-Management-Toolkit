<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An organization may ask for mixed-case letters, digits and symbols in
 * passwords, when an audit or a contract demands it. Off by default: NIST SP
 * 800-63B advises against such rules (ADR 0069).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core_security_settings', function (Blueprint $table): void {
            $table->boolean('password_composition_required')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('core_security_settings', function (Blueprint $table): void {
            $table->dropColumn('password_composition_required');
        });
    }
};
