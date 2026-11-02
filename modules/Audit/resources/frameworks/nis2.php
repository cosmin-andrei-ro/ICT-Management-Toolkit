<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

/*
| Directive (EU) 2022/2555 (NIS2): the governance, risk-management and
| reporting obligations an IT department evidences, summarized.
*/

return [
    'key' => 'nis2',
    'name' => 'NIS2 Directive',
    'version' => '(EU) 2022/2555',
    'has_soa' => false,
    'description' => 'Cybersecurity governance, risk-management measures and incident reporting for essential and important entities.',
    'groups' => [
        'Governance (Art. 20)' => [
            'Art. 20(1)' => ['Management approval and oversight', 'The management bodies approve the cybersecurity risk-management measures, oversee their implementation and can be held liable.'],
            'Art. 20(2)' => ['Management training', 'Members of the management bodies follow training, and staff are offered similar training regularly.'],
        ],
        'Risk-management measures (Art. 21)' => [
            'Art. 21(1)' => ['Appropriate and proportionate measures', 'Technical, operational and organisational measures to manage the risks to network and information systems, following an all-hazards approach.'],
            'Art. 21(2)(a)' => ['Risk analysis and information system security policies', 'Policies on risk analysis and on the security of information systems.'],
            'Art. 21(2)(b)' => ['Incident handling', 'Prevention, detection and handling of incidents.'],
            'Art. 21(2)(c)' => ['Business continuity and crisis management', 'Business continuity, such as backup management and disaster recovery, and crisis management.'],
            'Art. 21(2)(d)' => ['Supply chain security', 'Security of the supply chain, including the relationships with direct suppliers and service providers.'],
            'Art. 21(2)(e)' => ['Secure acquisition, development and maintenance', 'Security in acquiring, developing and maintaining network and information systems, including vulnerability handling and disclosure.'],
            'Art. 21(2)(f)' => ['Effectiveness assessment', 'Policies and procedures to assess the effectiveness of the risk-management measures.'],
            'Art. 21(2)(g)' => ['Cyber hygiene and training', 'Basic cyber hygiene practices and cybersecurity training.'],
            'Art. 21(2)(h)' => ['Cryptography and encryption', 'Policies and procedures on the use of cryptography and, where appropriate, encryption.'],
            'Art. 21(2)(i)' => ['HR security, access control and asset management', 'Human resources security, access control policies and asset management.'],
            'Art. 21(2)(j)' => ['Multifactor authentication and secured communications', 'Multifactor or continuous authentication, secured voice, video and text communications and secured emergency communications where appropriate.'],
        ],
        'Reporting (Art. 23)' => [
            'Art. 23(4)(a)' => ['Early warning within 24 hours', 'An early warning to the CSIRT or competent authority within 24 hours of becoming aware of a significant incident.'],
            'Art. 23(4)(b)' => ['Incident notification within 72 hours', 'An incident notification within 72 hours, with an initial assessment of severity and impact.'],
            'Art. 23(4)(d)' => ['Final report within one month', 'A final report within one month of the notification, with the root cause and the mitigation measures applied.'],
        ],
    ],
];
