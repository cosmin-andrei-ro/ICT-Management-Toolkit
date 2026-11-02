<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

/*
| ISO/IEC 27001:2022 Annex A: control identifiers with short titles written
| for this toolkit. ISO's own wording is copyrighted and is not included; an
| organization may paste the text of its licensed copy into each requirement.
*/

return [
    'key' => 'iso27001',
    'name' => 'ISO/IEC 27001:2022 Annex A',
    'version' => '2022',
    'has_soa' => true,
    'description' => 'Information security controls of Annex A, with identifiers and short titles written for this toolkit. Paste the wording of your licensed copy of the standard into each requirement if you need it.',
    'groups' => [
        'Organizational controls' => [
            '5.1' => 'Information security policy set', '5.2' => 'Security roles and responsibilities', '5.3' => 'Separation of conflicting duties',
            '5.4' => 'Management commitment to security', '5.5' => 'Relations with authorities', '5.6' => 'Relations with security interest groups',
            '5.7' => 'Threat intelligence', '5.8' => 'Security in projects', '5.9' => 'Inventory of information and assets',
            '5.10' => 'Acceptable use of information and assets', '5.11' => 'Return of assets', '5.12' => 'Information classification',
            '5.13' => 'Information labelling', '5.14' => 'Information transfer', '5.15' => 'Access control rules',
            '5.16' => 'Identity lifecycle', '5.17' => 'Authentication secrets', '5.18' => 'Granting and reviewing access rights',
            '5.19' => 'Security in supplier relationships', '5.20' => 'Security clauses in supplier agreements', '5.21' => 'ICT supply chain security',
            '5.22' => 'Monitoring and changes of supplier services', '5.23' => 'Security of cloud services', '5.24' => 'Incident management planning',
            '5.25' => 'Assessing security events', '5.26' => 'Responding to security incidents', '5.27' => 'Lessons from security incidents',
            '5.28' => 'Collecting evidence', '5.29' => 'Security during disruption', '5.30' => 'ICT readiness for continuity',
            '5.31' => 'Legal, regulatory and contractual requirements', '5.32' => 'Intellectual property', '5.33' => 'Protection of records',
            '5.34' => 'Privacy and personal data protection', '5.35' => 'Independent security review', '5.36' => 'Compliance with security policies and standards',
            '5.37' => 'Documented operating procedures',
        ],
        'People controls' => [
            '6.1' => 'Background screening', '6.2' => 'Security in employment terms', '6.3' => 'Security awareness and training',
            '6.4' => 'Disciplinary process', '6.5' => 'Duties after leaving or changing role', '6.6' => 'Confidentiality agreements',
            '6.7' => 'Remote working', '6.8' => 'Reporting security events',
        ],
        'Physical controls' => [
            '7.1' => 'Physical security perimeters', '7.2' => 'Physical entry control', '7.3' => 'Securing offices, rooms and facilities',
            '7.4' => 'Physical security monitoring', '7.5' => 'Protection against physical and environmental threats', '7.6' => 'Working in secure areas',
            '7.7' => 'Clear desk and clear screen', '7.8' => 'Equipment siting and protection', '7.9' => 'Assets used off-premises',
            '7.10' => 'Storage media', '7.11' => 'Supporting utilities', '7.12' => 'Cabling security',
            '7.13' => 'Equipment maintenance', '7.14' => 'Secure disposal or re-use of equipment',
        ],
        'Technological controls' => [
            '8.1' => 'User endpoint devices', '8.2' => 'Privileged access', '8.3' => 'Restricting access to information',
            '8.4' => 'Access to source code', '8.5' => 'Secure authentication', '8.6' => 'Capacity management',
            '8.7' => 'Malware protection', '8.8' => 'Technical vulnerability management', '8.9' => 'Configuration management',
            '8.10' => 'Deleting information', '8.11' => 'Data masking', '8.12' => 'Data leakage prevention',
            '8.13' => 'Backups', '8.14' => 'Redundant processing facilities', '8.15' => 'Logging',
            '8.16' => 'Monitoring activities', '8.17' => 'Clock synchronization', '8.18' => 'Privileged utility programs',
            '8.19' => 'Software installation on operational systems', '8.20' => 'Network security', '8.21' => 'Security of network services',
            '8.22' => 'Network segregation', '8.23' => 'Web filtering', '8.24' => 'Use of cryptography',
            '8.25' => 'Secure development life cycle', '8.26' => 'Application security requirements', '8.27' => 'Secure architecture and engineering',
            '8.28' => 'Secure coding', '8.29' => 'Security testing in development and acceptance', '8.30' => 'Outsourced development',
            '8.31' => 'Separate development, test and production', '8.32' => 'Change management', '8.33' => 'Test data',
            '8.34' => 'Protecting systems during audit testing',
        ],
    ],
];
