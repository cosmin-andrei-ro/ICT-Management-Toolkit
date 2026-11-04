<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticketing_service_approvers', function (Blueprint $table): void {
            $table->foreignUuid('service_id')->constrained('ticketing_services')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            $table->primary(['service_id', 'user_id']);
        });

        DB::statement('ALTER TABLE ticketing_tickets DROP CONSTRAINT ticketing_tickets_status_check');
        DB::statement("ALTER TABLE ticketing_tickets ADD CONSTRAINT ticketing_tickets_status_check CHECK (status IN ('awaiting_approval', 'new', 'assigned', 'in_progress', 'pending', 'resolved', 'closed', 'cancelled'))");
    }

    public function down(): void
    {
        // A request still waiting would break the old constraint: it goes on as a new ticket.
        DB::statement("UPDATE ticketing_tickets SET status = 'new' WHERE status = 'awaiting_approval'");
        DB::statement('ALTER TABLE ticketing_tickets DROP CONSTRAINT ticketing_tickets_status_check');
        DB::statement("ALTER TABLE ticketing_tickets ADD CONSTRAINT ticketing_tickets_status_check CHECK (status IN ('new', 'assigned', 'in_progress', 'pending', 'resolved', 'closed', 'cancelled'))");
        Schema::dropIfExists('ticketing_service_approvers');
    }
};
