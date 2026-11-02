<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Application\Options;

/**
 * The dropdown values every organization starts with (owner's decision: the
 * template's lists were not part of it; administrators change them).
 */
final class DefaultOptions
{
    /**
     * @return array<string, array<string, string>> Key => label, by list.
     */
    public static function lists(): array
    {
        return [
            'application_status' => [
                'planned' => 'Planned',
                'in_development' => 'In development',
                'in_production' => 'In production',
                'being_retired' => 'Being retired',
                'retired' => 'Retired',
            ],
            'business_criticality' => [
                'critical' => 'Critical',
                'high' => 'High',
                'medium' => 'Medium',
                'low' => 'Low',
            ],
            'cartography_profile' => [
                'core_business' => 'Core business application',
                'business_support' => 'Business support application',
                'management_reporting' => 'Management and reporting',
                'integration' => 'Integration / middleware',
                'infrastructure' => 'Infrastructure / technical service',
                'end_user' => 'End-user / productivity tool',
            ],
            'environments' => [
                'production' => 'Production',
                'pre_production' => 'Pre-production',
                'uat' => 'User acceptance testing (UAT)',
                'test' => 'Test',
                'development' => 'Development',
                'training' => 'Training',
                'disaster_recovery' => 'Disaster recovery',
            ],
            'document_types' => [
                'source_code' => 'Source code repository',
                'user_manual' => 'User manual',
                'admin_guide' => 'Administration guide',
                'vendor_documentation' => 'Vendor documentation',
                'architecture' => 'Architecture document',
                'contract_sla' => 'Contract / SLA',
                'security_assessment' => 'Security assessment / DPIA',
            ],
            'support_hours' => [
                '24x7' => '24/7',
                'business_hours' => 'Business hours (Mon–Fri 08:00–18:00)',
                'extended_hours' => 'Extended hours (Mon–Sat 07:00–22:00)',
                'best_effort' => 'Best effort',
            ],
            'data_classification' => [
                'public' => 'Public',
                'internal' => 'Internal',
                'confidential' => 'Confidential',
                'strictly_confidential' => 'Strictly confidential',
            ],
            'integration_direction' => [
                'inbound' => 'Inbound (into the application)',
                'outbound' => 'Outbound (from the application)',
                'bidirectional' => 'Both directions',
            ],
            'integration_type' => [
                'rest_api' => 'REST API',
                'soap' => 'SOAP web service',
                'file_transfer' => 'File transfer (SFTP / shared folder)',
                'message_queue' => 'Message queue / event stream',
                'database_link' => 'Database link',
                'ldap' => 'LDAP / directory',
                'email' => 'E-mail',
            ],
            'integration_frequency' => [
                'real_time' => 'Real time',
                'minutes' => 'Every few minutes',
                'hourly' => 'Hourly',
                'daily' => 'Daily',
                'weekly' => 'Weekly',
                'monthly' => 'Monthly',
                'on_demand' => 'On demand',
            ],
            'yes_no' => [
                'yes' => 'Yes',
                'no' => 'No',
                'partially' => 'Partially',
                'not_applicable' => 'Not applicable',
            ],
            'authentication_protocols' => [
                'ldaps' => 'LDAP / LDAPS',
                'kerberos' => 'Kerberos / Windows integrated',
                'saml2' => 'SAML 2.0',
                'oidc' => 'OpenID Connect',
                'oauth2' => 'OAuth 2.0',
                'local' => 'Local accounts (application database)',
            ],
            'support_teams' => [
                'business_owner_team' => 'Business Owner Team',
                'service_desk' => 'Service Desk (L1)',
                'application_support' => 'Application Support (L2/L3)',
                'business_analysts' => 'Technical Business Analysts Team',
                'development' => 'Development Team',
                'architecture' => 'Architecture Team',
                'rpa' => 'Robotic Process Automation (RPA) Team',
                'infrastructure' => 'Infrastructure Team',
                'network' => 'Network Team',
                'database' => 'Database Team',
                'iam' => 'Identity & Access Management (IAM) Team',
                'soc' => 'Security Operations (SOC) Team',
                'grc' => 'Security Governance, Risk & Compliance (GRC) Team',
                'audit' => 'Audit Team',
                'legal' => 'Legal Team',
            ],
        ];
    }
}
