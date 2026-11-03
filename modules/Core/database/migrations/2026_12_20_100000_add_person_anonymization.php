<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Anonymization of a person on request (GDPR Art. 17, ADR 0059). The audit log
 * stays append-only, with one exception: core_redact_person_in_audit_log()
 * may replace the person's name and e-mail inside the recorded values and
 * clear the IP address and browser of their own entries. Who, when, what and
 * on which record never change.
 */
return new class extends Migration
{
    private const string EVENTS = "'created', 'updated', 'deleted', 'restored', 'force_deleted',
        'role_assigned', 'role_revoked', 'permission_granted', 'permission_revoked',
        'login', 'login_failed', 'logout', 'lockout', 'login_denied',
        'exported', 'export_downloaded',
        'mfa_enabled', 'mfa_disabled', 'mfa_reset', 'mfa_failed', 'mfa_recovery_code_used', 'mfa_recovery_codes_regenerated',
        'password_changed', 'password_reset', 'invited', 'secret_revealed', 'security_key_added', 'security_key_removed',
        'account_locked', 'account_unlocked'";

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestampTz('anonymized_at')->nullable();
        });

        // An anonymized account has no way to sign in, whatever its source was.
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_credentials_match_source');
        DB::statement(self::credentialsCheck('OR (anonymized_at IS NOT NULL AND password IS NULL AND directory_guid IS NULL)'));

        DB::statement('ALTER TABLE core_audit_logs DROP CONSTRAINT core_audit_logs_event_check');
        DB::statement('ALTER TABLE core_audit_logs ADD CONSTRAINT core_audit_logs_event_check CHECK (event IN ('.self::EVENTS.", 'person_anonymized'))");

        // OR REPLACE: db:wipe (migrate:fresh) drops tables but leaves functions behind.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION core_audit_logs_immutable() RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
                -- Only during core_redact_person_in_audit_log(), and only the personal columns.
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

            CREATE OR REPLACE FUNCTION core_redact_person_in_audit_log(
                person uuid, person_name text, person_email text, replacement_name text, replacement_email text
            ) RETURNS integer
            LANGUAGE plpgsql AS $$
            DECLARE
                name_json text := to_jsonb(person_name)::text;
                email_json text := to_jsonb(person_email)::text;
                redacted integer;
            BEGIN
                PERFORM set_config('core.audit_redaction', 'on', true);

                -- Whole JSON string values only: "Ana Pop" is replaced, "Ana Popescu" is not.
                UPDATE core_audit_logs SET
                    old_values = CASE WHEN old_values IS NULL THEN NULL ELSE
                        replace(replace(old_values::text, name_json, to_jsonb(replacement_name)::text), email_json, to_jsonb(replacement_email)::text)::jsonb END,
                    new_values = CASE WHEN new_values IS NULL THEN NULL ELSE
                        replace(replace(new_values::text, name_json, to_jsonb(replacement_name)::text), email_json, to_jsonb(replacement_email)::text)::jsonb END,
                    ip_address = CASE WHEN user_id = person THEN NULL ELSE ip_address END,
                    user_agent = CASE WHEN user_id = person THEN NULL ELSE user_agent END
                WHERE user_id = person
                    OR position(name_json IN coalesce(old_values::text, '')) > 0 OR position(name_json IN coalesce(new_values::text, '')) > 0
                    OR position(email_json IN coalesce(old_values::text, '')) > 0 OR position(email_json IN coalesce(new_values::text, '')) > 0;

                GET DIAGNOSTICS redacted = ROW_COUNT;
                PERFORM set_config('core.audit_redaction', 'off', true);

                RETURN redacted;
            END;
            $$;
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS core_redact_person_in_audit_log(uuid, text, text, text, text)');
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION core_audit_logs_immutable() RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'core_audit_logs is append-only (% rejected)', TG_OP
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$;
            SQL);

        // Audit entries are append-only: keep the old check valid for new rows only.
        DB::statement('ALTER TABLE core_audit_logs DROP CONSTRAINT core_audit_logs_event_check');
        DB::statement('ALTER TABLE core_audit_logs ADD CONSTRAINT core_audit_logs_event_check CHECK (event IN ('.self::EVENTS.')) NOT VALID');

        DB::statement('ALTER TABLE users DROP CONSTRAINT users_credentials_match_source');
        DB::statement(self::credentialsCheck('').' NOT VALID');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('anonymized_at');
        });
    }

    private static function credentialsCheck(string $extra): string
    {
        return "ALTER TABLE users ADD CONSTRAINT users_credentials_match_source CHECK (
            (auth_source = 'local' AND password IS NOT NULL AND directory_guid IS NULL)
            OR (auth_source = 'ldap' AND password IS NULL AND directory_guid IS NOT NULL)
            OR (auth_source = 'sso' AND password IS NULL AND directory_guid IS NULL)
            {$extra}
        )";
    }
};
