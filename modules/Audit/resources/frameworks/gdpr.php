<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

/*
| Regulation (EU) 2016/679 (GDPR): the obligations an IT department helps to
| evidence, summarized.
*/

return [
    'key' => 'gdpr',
    'name' => 'GDPR',
    'version' => '(EU) 2016/679',
    'has_soa' => false,
    'description' => 'Obligations of the General Data Protection Regulation that IT systems and teams help to meet and evidence.',
    'groups' => [
        'Principles and accountability' => [
            'Art. 5(1)' => ['Processing principles', 'Lawfulness, fairness and transparency; purpose limitation; data minimisation; accuracy; storage limitation; integrity and confidentiality.'],
            'Art. 5(2)' => ['Accountability', 'The controller is responsible for, and able to demonstrate, compliance with the principles.'],
            'Art. 25' => ['Data protection by design and by default', 'Appropriate measures built into processing, and only the personal data necessary processed by default.'],
        ],
        'Processors and records' => [
            'Art. 28' => ['Processors', 'Only processors giving sufficient guarantees, bound by a contract with the required terms.'],
            'Art. 30' => ['Records of processing activities', 'A record of the processing activities under the organization\'s responsibility.'],
        ],
        'Security and breaches' => [
            'Art. 32' => ['Security of processing', 'Measures appropriate to the risk, such as encryption, resilience, restoration of availability and regular testing of their effectiveness.'],
            'Art. 33' => ['Notifying a breach to the authority', 'A personal data breach notified to the supervisory authority within 72 hours where it is likely to result in a risk.'],
            'Art. 34' => ['Communicating a breach to data subjects', 'The data subjects informed without undue delay when a breach is likely to result in a high risk to them.'],
        ],
        'Assessment and data protection officer' => [
            'Art. 35' => ['Data protection impact assessment', 'An assessment before processing likely to result in a high risk to the rights and freedoms of people.'],
            'Art. 37' => ['Data protection officer', 'A data protection officer designated where required, and their contact details published and communicated.'],
        ],
    ],
];
