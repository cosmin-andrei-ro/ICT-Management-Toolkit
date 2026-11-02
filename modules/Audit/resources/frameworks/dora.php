<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

/*
| Regulation (EU) 2022/2554 (DORA): ICT risk management, incident
| management, resilience testing and third-party risk, summarized.
*/

return [
    'key' => 'dora',
    'name' => 'DORA',
    'version' => '(EU) 2022/2554',
    'has_soa' => false,
    'description' => 'Digital operational resilience requirements for financial entities: ICT risk management, incidents, testing and third parties.',
    'groups' => [
        'ICT risk management (Chapter II)' => [
            'Art. 5' => ['Governance and organisation', 'The management body defines, approves, oversees and is accountable for the ICT risk-management framework.'],
            'Art. 6' => ['ICT risk-management framework', 'A sound, comprehensive and documented framework, reviewed at least yearly and audited.'],
            'Art. 7' => ['ICT systems, protocols and tools', 'Up-to-date, reliable, sufficiently capacious and resilient ICT systems.'],
            'Art. 8' => ['Identification', 'Business functions, information and ICT assets, their roles and dependencies identified, classified and documented.'],
            'Art. 9' => ['Protection and prevention', 'Security policies, strong authentication, access control, change and patch management to protect ICT systems.'],
            'Art. 10' => ['Detection', 'Mechanisms to promptly detect anomalous activities, including ICT network performance issues and incidents.'],
            'Art. 11' => ['Response and recovery', 'An ICT business continuity policy with response and recovery plans, tested regularly.'],
            'Art. 12' => ['Backup, restoration and recovery', 'Backup policies and procedures, restoration and recovery methods, tested periodically.'],
            'Art. 13' => ['Learning and evolving', 'Post-incident reviews, lessons learned, awareness programmes and training.'],
            'Art. 14' => ['Communication', 'Crisis communication plans for disclosing incidents to clients, counterparts and the public.'],
        ],
        'ICT-related incidents (Chapter III)' => [
            'Art. 17' => ['Incident management process', 'A process to detect, manage, record and notify ICT-related incidents, with root causes identified.'],
            'Art. 18' => ['Classification of incidents', 'Incidents and cyber threats classified by their impact, using the set criteria.'],
            'Art. 19' => ['Reporting of major incidents', 'Major ICT-related incidents reported to the competent authority with initial, intermediate and final reports.'],
        ],
        'Resilience testing (Chapter IV)' => [
            'Art. 24' => ['Testing programme', 'A sound and comprehensive digital operational resilience testing programme.'],
            'Art. 25' => ['Testing of ICT tools and systems', 'Tests such as vulnerability assessments, scenario-based, performance and penetration tests, at least yearly for critical systems.'],
            'Art. 26' => ['Advanced threat-led penetration testing', 'Threat-led penetration testing at least every three years where required.'],
            'Art. 27' => ['Requirements for testers', 'Testers with the required suitability, reputation and certifications.'],
        ],
        'ICT third-party risk (Chapter V)' => [
            'Art. 28' => ['Third-party risk management', 'A strategy on ICT third-party risk, a register of information on contractual arrangements and pre-contract assessments.'],
        ],
    ],
];
