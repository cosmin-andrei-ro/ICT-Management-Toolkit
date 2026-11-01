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
        // No foreign keys on purpose: the history must outlive the rows it describes,
        // and the immutability trigger would turn any ON DELETE action into an error.
        Schema::create('core_audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('event', 32);
            $table->string('auditable_type');
            $table->uuid('auditable_id');
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('request_id', 64)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id', 'created_at']);
            $table->index(['organization_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE core_audit_logs ADD CONSTRAINT core_audit_logs_event_check
                CHECK (event IN ('created', 'updated', 'deleted', 'restored', 'force_deleted'))
            SQL);

        // OR REPLACE: db:wipe (migrate:fresh) drops tables but leaves functions behind.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION core_audit_logs_immutable() RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'core_audit_logs is append-only (% rejected)', TG_OP
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$;

            CREATE TRIGGER core_audit_logs_no_update_delete
                BEFORE UPDATE OR DELETE ON core_audit_logs
                FOR EACH ROW EXECUTE FUNCTION core_audit_logs_immutable();

            CREATE TRIGGER core_audit_logs_no_truncate
                BEFORE TRUNCATE ON core_audit_logs
                FOR EACH STATEMENT EXECUTE FUNCTION core_audit_logs_immutable();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('core_audit_logs');
        DB::unprepared('DROP FUNCTION IF EXISTS core_audit_logs_immutable()');
    }
};
