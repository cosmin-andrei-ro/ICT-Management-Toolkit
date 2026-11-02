<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Domain\Sheet;

/**
 * The chapters of the application technical documentation, following the
 * owner's template (docs/templates/application-technical-documentation.md).
 * Chapters are added here as they are built; forms, validation, completeness
 * and display all follow this definition.
 */
final class SheetSchema
{
    /** Chapter 0: document control, at the top of the template. */
    public const int DOCUMENT_CONTROL = 0;

    /**
     * @return array<int, list<Section>> By chapter number.
     */
    public static function chapters(): array
    {
        $chapters = [];

        foreach (self::sections() as $section) {
            $chapters[$section->chapter][] = $section;
        }

        return $chapters;
    }

    public static function section(string $key): ?Section
    {
        foreach (self::sections() as $section) {
            if ($section->key === $key) {
                return $section;
            }
        }

        return null;
    }

    public static function chapterTitle(int $chapter): string
    {
        return __('applications::sheet.chapters.'.$chapter);
    }

    /**
     * @return list<Section>
     */
    public static function sections(): array
    {
        return [
            new Section('doc', self::DOCUMENT_CONTROL, [
                Field::user('document_owner', required: true),
                Field::text('document_location'),
            ]),
            new Section('1_1', 1, [
                Field::textarea('description', required: true),
            ]),
            new Section('1_2_1', 1, [
                Field::contacts('internal_support', 'support_teams'),
            ]),
            new Section('1_2_2', 1, [
                Field::supplier('supplier'),
                Field::contract('contract'),
                Field::url('support_portal'),
                Field::email('support_email'),
            ]),
            new Section('1_3', 1, [
                Field::option('status', 'application_status', required: true),
                Field::option('criticality', 'business_criticality', required: true),
                Field::choices('exposure', ['internal', 'internet', 'third_party']),
                Field::option('cartography', 'cartography_profile', allowOther: true),
            ]),
            new Section('1_4', 1, [
                Field::table('environments', [
                    Field::option('environment', 'environments', required: true, allowOther: true),
                    Field::url('url'),
                    Field::text('purpose'),
                    Field::text('access'),
                ], required: true),
            ]),
            new Section('1_5', 1, [
                Field::table('documents', [
                    Field::option('type', 'document_types', required: true, allowOther: true),
                    Field::text('name', required: true),
                    Field::url('link'),
                ]),
            ]),
            new Section('1_6', 1, [
                Field::text('availability'),
                Field::option('support_hours', 'support_hours', allowOther: true),
                Field::text('response_times'),
                Field::text('maintenance_window'),
                Field::text('change_freeze'),
                Field::text('maintenance_notification'),
            ]),
            new Section('2_1', 2, [
                Field::option('classification', 'data_classification', required: true),
                Field::dataCategories('categories'),
            ]),
            new Section('2_2', 2, [
                Field::diagram('diagram'),
            ]),
            new Section('2_3', 2, [
                Field::table('functionalities', [
                    Field::text('module', required: true),
                    Field::text('menu'),
                    Field::text('functionality', required: true),
                    Field::text('description', max: 1000),
                    Field::text('input', max: 1000),
                    Field::text('output', max: 1000),
                ]),
            ]),
            new Section('2_4', 2, [
                Field::table('integrations', [
                    Field::text('name', required: true),
                    Field::text('system', required: true),
                    Field::option('direction', 'integration_direction', required: true),
                    Field::option('type', 'integration_type', allowOther: true),
                    Field::text('data', max: 1000),
                    Field::option('frequency', 'integration_frequency', allowOther: true),
                    Field::text('owner'),
                ]),
            ]),
            new Section('3_1', 3, [
                Field::option('ad_integrated', 'yes_no'),
                Field::option('protocol', 'authentication_protocols', allowOther: true),
                Field::text('ad_domain'),
                Field::option('iam_integrated', 'yes_no'),
                Field::text('iam_resource'),
                Field::option('mfa', 'yes_no'),
                Field::text('mfa_method'),
                Field::option('sso', 'yes_no'),
            ]),
            new Section('3_2_1', 3, [
                Field::textarea('procedure'),
            ]),
            new Section('3_2_2', 3, [
                Field::textarea('procedure'),
            ]),
            new Section('3_3', 3, [
                Field::table('roles', [
                    Field::text('name', required: true),
                    Field::text('description', max: 1000),
                    Field::text('group'),
                ]),
            ]),
            new Section('3_4', 3, [
                Field::permissions('matrix'),
            ]),
            new Section('4_1', 4, [
                Field::text('dns_names', max: 1000),
                Field::text('load_balancer'),
                Field::option('balancing', 'load_balancing', allowOther: true),
                Field::option('tls_termination', 'tls_termination', allowOther: true),
            ]),
            new Section('4_2', 4, [
                Field::diagram('diagram'),
            ]),
            new Section('4_3', 4, [
                Field::table('rules', [
                    Field::text('source', required: true),
                    Field::text('destination', required: true),
                    Field::text('port', required: true, max: 100),
                    Field::option('direction', 'integration_direction'),
                    Field::option('protocol', 'network_protocols', allowOther: true),
                    Field::option('encryption', 'encryption_methods', allowOther: true),
                    Field::text('purpose'),
                    Field::text('ticket', max: 64),
                ]),
            ]),
            new Section('5_1', 5, [
                Field::option('hosting_model', 'hosting_models', allowOther: true),
                Field::text('provider'),
                Field::option('platform', 'platforms', allowOther: true),
            ]),
            new Section('5_2', 5, [
                Field::configurationItems('items'),
                Field::table('components', [
                    Field::option('component', 'component_types', required: true, allowOther: true),
                    Field::text('server', required: true),
                    Field::text('ip', max: 100),
                    Field::option('os', 'operating_systems', allowOther: true),
                    Field::text('type'),
                    Field::text('description', max: 1000),
                    Field::option('environment', 'environments', allowOther: true),
                    Field::text('ticket', max: 64),
                ]),
            ]),
            new Section('6_1', 6, [
                Field::option('application_type', 'application_types', allowOther: true),
                Field::option('access_method', 'access_methods', allowOther: true),
                Field::text('tiers', max: 1000),
                Field::textarea('notes'),
            ]),
            new Section('6_2', 6, [
                Field::table('backends', [
                    Field::text('framework', required: true),
                    Field::text('framework_version', max: 64),
                    Field::text('servers'),
                    Field::text('role'),
                    Field::option('app_server', 'app_servers', allowOther: true),
                    Field::text('app_server_version', max: 64),
                    Field::option('language', 'languages', allowOther: true),
                    Field::text('paths', max: 1000),
                ]),
            ]),
            new Section('6_3', 6, [
                Field::table('frontends', [
                    Field::text('name', required: true),
                    Field::option('client_type', 'client_types', allowOther: true),
                    Field::text('technology'),
                    Field::text('version', max: 64),
                    Field::text('language'),
                    Field::text('build_tool'),
                    Field::option('distribution', 'distribution_methods', allowOther: true),
                    Field::text('prerequisites'),
                ]),
            ]),
            new Section('6_4', 6, [
                Field::table('databases', [
                    Field::option('dbms', 'dbms', required: true, allowOther: true),
                    Field::text('version', max: 64),
                    Field::text('host'),
                    Field::text('schemas', max: 1000),
                    Field::option('high_availability', 'high_availability', allowOther: true),
                ]),
            ]),
            new Section('6_4_1', 6, [
                Field::diagram('diagram'),
            ]),
            new Section('6_5', 6, [
                Field::table('middleware', [
                    Field::text('product', required: true),
                    Field::text('version', max: 64),
                    Field::text('endpoint'),
                    Field::text('purpose', max: 1000),
                ]),
            ]),
            new Section('6_6', 6, [
                Field::table('libraries', [
                    Field::text('name', required: true),
                    Field::text('version', max: 64),
                    Field::option('license', 'licenses', allowOther: true),
                    Field::text('vendor'),
                    Field::date('support_until'),
                ]),
            ]),
            new Section('7', 7, [
                Field::table('accounts', [
                    Field::text('username', required: true),
                    Field::option('iam_category', 'iam_categories', allowOther: true),
                    Field::text('user_type'),
                    Field::text('purpose', max: 1000),
                    Field::text('systems'),
                    Field::text('owner'),
                    Field::option('rotation', 'rotation_periods', allowOther: true),
                    Field::text('credential_location'),
                    Field::text('ticket', max: 64),
                ]),
            ]),
            new Section('8_1', 8, [
                Field::table('certificates', [
                    Field::text('name', required: true),
                    Field::option('type', 'certificate_types', allowOther: true),
                    Field::text('used_for'),
                    Field::text('issuer'),
                    // Not in the template's table: needed for the expiry reminders.
                    Field::date('expires_on'),
                    Field::text('tracking'),
                    Field::text('lead_time', max: 100),
                    Field::text('owner'),
                ]),
            ]),
            new Section('8_2', 8, [
                Field::table('secrets', [
                    Field::text('name', required: true),
                    Field::option('type', 'secret_types', allowOther: true),
                    Field::text('used_by'),
                    Field::text('location', required: true),
                    Field::option('rotation', 'rotation_periods', allowOther: true),
                    Field::text('owner'),
                ]),
            ]),
            new Section('8_3', 8, [
                Field::table('requirements', [
                    Field::text('regulation', required: true),
                    Field::text('scope', max: 1000),
                    Field::text('evidence', max: 1000),
                    Field::text('last_audit', max: 64),
                    Field::text('owner'),
                ]),
            ]),
            new Section('9_1', 9, [
                Field::text('app_location'),
                Field::text('app_forwarded'),
                Field::text('app_method'),
                Field::text('os_location'),
                Field::text('os_forwarded'),
                Field::text('os_method'),
                Field::text('db_location'),
                Field::text('db_forwarded'),
                Field::text('db_method'),
            ]),
            new Section('9_2', 9, [
                Field::table('logs', [
                    Field::text('name', required: true),
                    Field::option('type', 'log_types', allowOther: true),
                    Field::text('description', max: 1000),
                    Field::text('server'),
                    Field::text('path', max: 1000),
                    Field::text('retention'),
                ]),
            ]),
            new Section('9_3', 9, [
                Field::text('tools'),
                Field::text('dashboards', max: 1000),
                Field::text('health_urls', max: 1000),
                Field::table('alerts', [
                    Field::text('name', required: true),
                    Field::text('condition', max: 1000),
                    Field::option('severity', 'alert_severities', allowOther: true),
                    Field::text('recipients'),
                    Field::option('channel', 'alert_channels', allowOther: true),
                    Field::text('action', max: 1000),
                ]),
            ]),
            new Section('10_1', 10, [
                Field::processes('processes'),
                Field::insight('bia'),
                Field::text('departments', max: 1000),
                Field::textarea('impact'),
                Field::text('critical_periods', max: 1000),
                Field::text('key_roles', max: 1000),
                Field::text('minimum_staffing', max: 1000),
                Field::text('other_resources', max: 1000),
                Field::text('mtpd', max: 100),
                Field::text('rpo', max: 100),
                Field::text('rto', max: 100),
                Field::text('dr_site'),
                Field::option('recovery_priority', 'recovery_priorities', allowOther: true),
            ]),
            new Section('10_2', 10, [
                Field::table('backups', [
                    Field::text('what', required: true),
                    Field::option('method', 'backup_methods', allowOther: true),
                    Field::text('frequency'),
                    Field::text('retention'),
                    Field::text('owner'),
                    Field::text('ticket', max: 64),
                ]),
            ]),
            new Section('10_3', 10, [
                Field::insight('dependencies'),
                Field::table('dependencies', [
                    Field::text('system', required: true),
                    Field::option('direction', 'dependency_directions'),
                    Field::option('criticality', 'business_criticality'),
                    Field::text('order', max: 16),
                    Field::text('notes', max: 1000),
                ]),
            ]),
            new Section('10_4', 10, [
                Field::textarea('workarounds'),
            ]),
            new Section('10_5', 10, [
                Field::insight('recovery_plans'),
                Field::textarea('procedure'),
            ]),
            new Section('11', 11, [
                Field::url('repository'),
                Field::text('change_management', max: 1000),
            ]),
            new Section('11_1', 11, [
                Field::textarea('procedure'),
            ]),
            new Section('11_2', 11, [
                Field::textarea('procedure'),
            ]),
            new Section('12', 12, [
                Field::table('jobs', [
                    Field::text('name', required: true),
                    Field::option('scheduler', 'schedulers', allowOther: true),
                    Field::text('schedule'),
                    Field::text('description', max: 1000),
                    Field::text('dependencies'),
                    Field::text('if_fails', max: 1000),
                ]),
            ]),
            new Section('13_1', 13, [
                Field::table('steps', [
                    Field::text('step', max: 16),
                    Field::text('component', required: true),
                    Field::text('server'),
                    Field::text('command', max: 1000),
                    Field::text('verification', max: 1000),
                ]),
            ]),
            new Section('13_2', 13, [
                Field::textarea('checks'),
            ]),
            new Section('13_3', 13, [
                Field::table('tasks', [
                    Field::text('task', required: true),
                    Field::option('frequency', 'task_frequencies', allowOther: true),
                    Field::text('procedure', max: 1000),
                ]),
            ]),
            new Section('14', 14, [
                Field::table('issues', [
                    Field::text('symptom', required: true, max: 1000),
                    Field::text('cause', max: 1000),
                    Field::text('resolution', max: 2000),
                    Field::text('ticket', max: 64),
                ]),
            ]),
            new Section('15_1', 15, [
                Field::option('reason', 'decommission_reasons', allowOther: true),
                Field::text('replaced_by'),
                Field::text('approval'),
                Field::text('retention', max: 1000),
                Field::text('archive_location', max: 1000),
            ]),
            new Section('15_2', 15, [
                Field::table('checklist', [
                    Field::text('step', max: 16),
                    Field::text('activity', required: true, max: 1000),
                    Field::text('owner'),
                    Field::text('ticket', max: 64),
                    Field::option('status', 'checklist_status', allowOther: true),
                ]),
            ]),
            new Section('16', 16, [
                Field::insight('glossary'),
                Field::table('terms', [
                    Field::text('term', required: true, max: 64),
                    Field::text('meaning', required: true, max: 1000),
                ]),
            ]),
        ];
    }
}
