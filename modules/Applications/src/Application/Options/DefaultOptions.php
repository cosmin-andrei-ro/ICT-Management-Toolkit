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
            'load_balancing' => [
                'round_robin' => 'Round robin',
                'least_connections' => 'Least connections',
                'sticky' => 'Sticky sessions (IP hash / cookie)',
                'active_passive' => 'Active / passive',
                'none' => 'No load balancing',
            ],
            'tls_termination' => [
                'load_balancer' => 'On the load balancer',
                'reverse_proxy' => 'On the reverse proxy',
                'application_server' => 'On the application server',
                'end_to_end' => 'End to end (re-encrypted to the servers)',
                'none' => 'No TLS',
            ],
            'network_protocols' => [
                'https' => 'HTTPS',
                'http' => 'HTTP',
                'tcp' => 'TCP',
                'udp' => 'UDP',
                'ldaps' => 'LDAPS',
                'ssh' => 'SSH / SFTP',
                'smb' => 'SMB',
                'smtp' => 'SMTP',
                'database' => 'Database (SQL)',
            ],
            'encryption_methods' => [
                'tls13' => 'TLS 1.3',
                'tls12' => 'TLS 1.2',
                'ssh' => 'SSH',
                'ipsec' => 'IPsec VPN',
                'none' => 'Not encrypted',
            ],
            'hosting_models' => [
                'on_premises' => 'On premises',
                'private_cloud' => 'Private cloud',
                'iaas' => 'Public cloud (IaaS)',
                'paas' => 'Public cloud (PaaS)',
                'saas' => 'SaaS',
                'hybrid' => 'Hybrid',
            ],
            'platforms' => [
                'vmware' => 'VMware vSphere',
                'hyper_v' => 'Microsoft Hyper-V',
                'kubernetes' => 'Kubernetes',
                'docker' => 'Docker / containers',
                'physical' => 'Physical servers',
                'azure' => 'Microsoft Azure',
                'aws' => 'Amazon Web Services',
                'gcp' => 'Google Cloud',
            ],
            'component_types' => [
                'web_server' => 'Web server',
                'application_server' => 'Application server',
                'worker' => 'Worker / batch server',
                'database_server' => 'Database server',
                'load_balancer' => 'Load balancer',
                'proxy' => 'Proxy',
                'file_server' => 'File / storage server',
            ],
            'operating_systems' => [
                'windows_2022' => 'Windows Server 2022',
                'windows_2025' => 'Windows Server 2025',
                'windows_2019' => 'Windows Server 2019',
                'rhel_9' => 'Red Hat Enterprise Linux 9',
                'rhel_8' => 'Red Hat Enterprise Linux 8',
                'ubuntu_2404' => 'Ubuntu 24.04 LTS',
                'ubuntu_2204' => 'Ubuntu 22.04 LTS',
                'debian_12' => 'Debian 12',
                'appliance' => 'Appliance / vendor OS',
            ],
            'application_types' => [
                'web' => 'Web application',
                'desktop' => 'Desktop application',
                'client_server' => 'Client / server',
                'mobile' => 'Mobile application',
                'hybrid' => 'Hybrid (web and desktop or mobile)',
                'service' => 'Background service / batch',
            ],
            'access_methods' => [
                'browser' => 'Web browser',
                'desktop_client' => 'Installed desktop client',
                'mobile_app' => 'Mobile app',
                'remote_desktop' => 'Remote desktop / Citrix',
                'api' => 'API only',
            ],
            'app_servers' => [
                'apache' => 'Apache HTTP Server',
                'nginx' => 'Nginx',
                'iis' => 'Microsoft IIS',
                'tomcat' => 'Apache Tomcat',
                'php_fpm' => 'PHP-FPM',
                'nodejs' => 'Node.js',
            ],
            'languages' => [
                'php' => 'PHP',
                'java' => 'Java',
                'dotnet' => 'C# / .NET',
                'python' => 'Python',
                'javascript' => 'JavaScript / TypeScript',
                'go' => 'Go',
            ],
            'client_types' => [
                'web_portal' => 'Web portal',
                'mobile_app' => 'Mobile app',
                'desktop_client' => 'Desktop client',
                'admin_console' => 'Administration console',
            ],
            'distribution_methods' => [
                'web' => 'Web (nothing to install)',
                'intune' => 'Microsoft Intune',
                'sccm' => 'Configuration Manager (SCCM)',
                'app_store' => 'App Store / Google Play',
                'manual' => 'Manual installer',
            ],
            'dbms' => [
                'postgresql' => 'PostgreSQL',
                'oracle' => 'Oracle Database',
                'sql_server' => 'Microsoft SQL Server',
                'mysql' => 'MySQL / MariaDB',
                'mongodb' => 'MongoDB',
            ],
            'high_availability' => [
                'none' => 'None',
                'replica' => 'Replica (standby)',
                'cluster' => 'Cluster',
                'always_on' => 'SQL Server Always On',
                'data_guard' => 'Oracle Data Guard',
            ],
            'licenses' => [
                'mit' => 'MIT',
                'apache2' => 'Apache 2.0',
                'bsd' => 'BSD',
                'lgpl' => 'LGPL',
                'gpl' => 'GPL',
                'agpl' => 'AGPL',
                'commercial' => 'Commercial',
            ],
            'iam_categories' => [
                'service_account' => 'Service account',
                'technical_user' => 'Technical user',
                'application_account' => 'Application account',
                'privileged_account' => 'Privileged administration account',
            ],
            'rotation_periods' => [
                '90_days' => 'Every 90 days',
                '180_days' => 'Every 180 days',
                'yearly' => 'Every year',
                'managed' => 'Managed automatically (PAM / vault)',
                'on_compromise' => 'Only when compromised',
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
