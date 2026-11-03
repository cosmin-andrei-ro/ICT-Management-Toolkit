<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Configurable retention (CLAUDE.md §6, ADR 0060): the organization's periods,
 * and two more controlled exceptions to the append-only audit log. Entries
 * older than the period are deleted by core_purge_audit_log(), and the fields
 * of a record whose content was removed (an anonymized ticket) are taken out
 * of its entries by core_redact_record_in_audit_log().
 */
return new class extends Migration
{
    private const string EVENTS = "'created', 'updated', 'deleted', 'restored', 'force_deleted',
        'role_assigned', 'role_revoked', 'permission_granted', 'permission_revoked',
        'login', 'login_failed', 'logout', 'lockout', 'login_denied',
        'exported', 'export_downloaded',
        'mfa_enabled', 'mfa_disabled', 'mfa_reset', 'mfa_failed', 'mfa_recovery_code_used', 'mfa_recovery_codes_regenerated',
        'password_changed', 'password_reset', 'invited', 'secret_revealed', 'security_key_added', 'security_key_removed',
        'account_locked', 'account_unlocked', 'person_anonymized'";

    public function up(): void
    {
        Schema::create('core_retention_settings', function (Blueprint $table): void {
            $table->foreignUuid('organization_id')->primary()->constrained('core_organizations')->cascadeOnDelete();
            // Policy key => years, or null to keep forever; a missing key takes the policy's default.
            $table->jsonb('periods')->default('{}');
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE core_audit_logs DROP CONSTRAINT core_audit_logs_event_check');
        DB::statement('ALTER TABLE core_audit_logs ADD CONSTRAINT core_audit_logs_event_check CHECK (event IN ('.self::EVENTS.", 'retention_applied'))");

        // OR REPLACE: db:wipe (migrate:fresh) drops tables but leaves functions behind.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION core_audit_logs_immutable() RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
                -- Only during core_redact_*(), and only the personal columns.
                IF TG_OP = 'UPDATE'
                    AND current_setting('core.audit_redaction', true) = 'on'
                    AND NEW.id = OLD.id
                    AND NEW.organization_id IS NOT DISTINCT FROM OLD.organization_id
                    AND NEW.user_id IS NOT DISTINCT FROM OLD.user_id
                    AND NEW.event = OLD.event
                    AND NEW.auditable_type IS NOT DISTINCT FROM OLD.auditable_type
                    AND NEW.auditable_id IS NOT DISTINCT FROM OLD.auditable_id
                    AND NEW.request_id IS NOT DISTINCT FROM OLD.request_id
                    AND NEW.created_at = OLD.created_at THEN
                    RETURN NEW;
                END IF;

                -- Only during core_purge_audit_log(), which deletes past the retention period.
                IF TG_OP = 'DELETE' AND current_setting('core.audit_retention', true) = 'on' THEN
                    RETURN OLD;
                END IF;

                RAISE EXCEPTION 'core_audit_logs is append-only (% rejected)', TG_OP
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$;

            CREATE OR REPLACE FUNCTION core_purge_audit_log(organization uuid, older_than timestamptz) RETURNS integer
            LANGUAGE plpgsql AS $$
            DECLARE
                purged integer;
            BEGIN
                PERFORM set_config('core.audit_retention', 'on', true);

                -- A null organization purges the entries that belong to none (sign-ins, people).
                DELETE FROM core_audit_logs
                WHERE created_at < older_than
                    AND organization_id IS NOT DISTINCT FROM organization;

                GET DIAGNOSTICS purged = ROW_COUNT;
                PERFORM set_config('core.audit_retention', 'off', true);

                RETURN purged;
            END;
            $$;

            CREATE OR REPLACE FUNCTION core_redact_record_in_audit_log(record_type text, record_id uuid, fields text[]) RETURNS integer
            LANGUAGE plpgsql AS $$
            DECLARE
                redacted integer;
            BEGIN
                PERFORM set_config('core.audit_redaction', 'on', true);

                UPDATE core_audit_logs SET
                    old_values = CASE WHEN old_values IS NULL THEN NULL ELSE old_values - fields END,
                    new_values = CASE WHEN new_values IS NULL THEN NULL ELSE new_values - fields END
                WHERE auditable_type = record_type AND auditable_id = record_id
                    AND (old_values ?| fields OR new_values ?| fields);

                GET DIAGNOSTICS redacted = ROW_COUNT;
                PERFORM set_config('core.audit_redaction', 'off', true);

                RETURN redacted;
            END;
            $$;
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS core_purge_audit_log(uuid, timestamptz)');
        DB::unprepared('DROP FUNCTION IF EXISTS core_redact_record_in_audit_log(text, uuid, text[])');
        // Back to the anonymization-only exception (ADR 0059).
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION core_audit_logs_immutable() RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'UPDATE'
                    AND current_setting('core.audit_redaction', true) = 'on'
                    AND NEW.id = OLD.id
                    AND NEW.organization_id IS NOT DISTINCT FROM OLD.organization_id
                    AND NEW.user_id IS NOT DISTINCT FROM OLD.user_id
                    AND NEW.event = OLD.event
                    AND NEW.auditable_type IS NOT DISTINCT FROM OLD.auditable_type
                    AND NEW.auditable_id IS NOT DISTINCT FROM OLD.auditable_id
                    AND NEW.request_id IS NOT DISTINCT FROM OLD.request_id
                    AND NEW.created_at = OLD.created_at THEN
                    RETURN NEW;
                END IF;

                RAISE EXCEPTION 'core_audit_logs is append-only (% rejected)', TG_OP
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$;
            SQL);

        // Audit entries are append-only: keep the old check valid for new rows only.
        DB::statement('ALTER TABLE core_audit_logs DROP CONSTRAINT core_audit_logs_event_check');
        DB::statement('ALTER TABLE core_audit_logs ADD CONSTRAINT core_audit_logs_event_check CHECK (event IN ('.self::EVENTS.')) NOT VALID');

        Schema::dropIfExists('core_retention_settings');
    }
};
