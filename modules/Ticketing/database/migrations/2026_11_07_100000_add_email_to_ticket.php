<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Email-to-ticket (Stage 6, ADR 0015): IMAP mailboxes read on a schedule, and a
 * log of every message read, which also makes processing idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticketing_mailboxes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->restrictOnDelete();
            $table->string('name');
            // The support address people write to; also the Reply-To of ticket emails.
            $table->string('address', 320);
            $table->string('host');
            $table->unsignedInteger('port');
            $table->string('encryption', 16);
            $table->string('username');
            // Encrypted by the model (APP_KEY); never shown again once saved.
            $table->text('password');
            $table->timestampTz('credentials_updated_at')->nullable();
            $table->string('folder')->default('INBOX');
            // Empty: processed messages are only marked as read.
            $table->string('processed_folder')->nullable();
            $table->uuid('queue_id')->nullable();
            $table->string('ticket_type', 32)->default('incident');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('last_checked_at')->nullable();
            // A short error code, never the server's text (it may echo credentials).
            $table->string('last_error', 32)->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['organization_id', 'id']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE ticketing_mailboxes
                ADD CONSTRAINT ticketing_mailboxes_encryption_check CHECK (encryption IN ('ssl', 'starttls', 'none')),
                ADD CONSTRAINT ticketing_mailboxes_port_check CHECK (port BETWEEN 1 AND 65535),
                ADD CONSTRAINT ticketing_mailboxes_ticket_type_check CHECK (ticket_type IN ('incident', 'service_request')),
                ADD CONSTRAINT ticketing_mailboxes_queue_fk FOREIGN KEY (organization_id, queue_id)
                    REFERENCES ticketing_queues (organization_id, id) ON DELETE SET NULL (queue_id)
            SQL);
        // One mailbox per address across the instance: two organizations reading the same inbox would race.
        DB::statement('CREATE UNIQUE INDEX ticketing_mailboxes_address_unique ON ticketing_mailboxes (lower(address)) WHERE deleted_at IS NULL');

        Schema::create('ticketing_inbound_emails', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('core_organizations')->cascadeOnDelete();
            $table->foreignUuid('mailbox_id')->constrained('ticketing_mailboxes')->cascadeOnDelete();
            // sha256 of the Message-ID (or of the message when it has none).
            $table->char('message_key', 64);
            $table->string('from_address', 320);
            $table->string('subject')->nullable();
            $table->string('outcome', 32);
            $table->string('detail')->nullable();
            $table->uuid('ticket_id')->nullable();
            $table->timestampTz('received_at')->nullable();
            $table->timestampTz('processed_at');

            $table->index(['mailbox_id', 'from_address', 'processed_at']);
            $table->index('processed_at');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE ticketing_inbound_emails
                ADD CONSTRAINT ticketing_inbound_emails_outcome_check CHECK (outcome IN ('created', 'commented', 'ignored_unknown_sender', 'ignored_automatic', 'ignored_spoofed', 'ignored_rate_limited', 'ignored_too_large', 'failed')),
                ADD CONSTRAINT ticketing_inbound_emails_ticket_fk FOREIGN KEY (organization_id, ticket_id)
                    REFERENCES ticketing_tickets (organization_id, id) ON DELETE SET NULL (ticket_id)
            SQL);
        // A message is handled once; a failed attempt does not block a later retry.
        DB::statement("CREATE UNIQUE INDEX ticketing_inbound_emails_once ON ticketing_inbound_emails (mailbox_id, message_key) WHERE outcome <> 'failed'");
    }

    public function down(): void
    {
        Schema::dropIfExists('ticketing_inbound_emails');
        Schema::dropIfExists('ticketing_mailboxes');
    }
};
