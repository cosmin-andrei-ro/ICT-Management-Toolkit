<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A mailbox may accept only senders the mail server verified (SPF or DMARC
 * pass). Off by default: staff messages are always held to it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticketing_mailboxes', function (Blueprint $table): void {
            $table->boolean('require_verified_senders')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('ticketing_mailboxes', function (Blueprint $table): void {
            $table->dropColumn('require_verified_senders');
        });
    }
};
