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
        ];
    }
}
