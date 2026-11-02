<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

/*
| The starter catalog of threats and vulnerabilities (inspired by ISO/IEC 27005
| annex A and ENISA's threat landscape), created once per organization and
| editable afterwards.
*/

return [
    'defaults' => [
        'threat' => [
            'Ransomware and other malware' => 'Malicious software that encrypts, destroys or steals data.',
            'Phishing and social engineering' => 'People tricked into giving credentials, money or access.',
            'Unauthorized access' => 'Someone uses accounts or systems they are not entitled to.',
            'Data theft or leakage' => 'Confidential or personal data disclosed outside the organization.',
            'Denial of service' => 'A service made unavailable by an attack or by overload.',
            'Insider misuse' => 'An employee or contractor misuses legitimate access.',
            'Human error' => 'A mistake in operation, configuration or data handling.',
            'Software failure' => 'A defect, failed update or corruption makes software unusable.',
            'Hardware failure' => 'Servers, storage or network equipment break down.',
            'Power failure' => 'Loss of electricity or of its backup.',
            'Telecommunication failure' => 'Loss of network, internet or telephony links.',
            'Fire' => 'Fire in a data center, office or site.',
            'Water damage' => 'Flood, leak or fire extinguishing water.',
            'Natural disaster' => 'Earthquake, storm or other natural event affecting a site.',
            'Theft or loss of equipment' => 'Laptops, phones or media lost or stolen.',
            'Supplier failure' => 'A supplier stops delivering a product or service it is contracted for.',
            'Supply-chain compromise' => 'An attacker reaches the organization through a supplier or its software.',
            'Key staff unavailable' => 'People with critical knowledge or roles are absent or leave.',
            'Breach of law or contract' => 'The organization fails a legal, regulatory or contractual obligation.',
            'Eavesdropping or interception' => 'Communications read or altered in transit.',
        ],
        'vulnerability' => [
            'Missing security updates' => 'Systems or software not patched in time.',
            'Weak authentication or no MFA' => 'Passwords alone protect access to important systems.',
            'Excessive access rights' => 'People or accounts have more rights than they need.',
            'Shared or generic accounts' => 'Actions cannot be traced to a person.',
            'Unsupported software' => 'Software past its end of support receives no fixes.',
            'Single point of failure' => 'One component, site or person whose loss stops the service.',
            'No tested backup' => 'Backups missing, incomplete or never restored in a test.',
            'Insufficient logging and monitoring' => 'Attacks or failures are noticed late or not at all.',
            'Unencrypted data in transit' => 'Data travels over networks without encryption.',
            'Unencrypted data at rest' => 'Stored data or backups readable if copied.',
            'Insecure configuration' => 'Default settings, open services or weak hardening.',
            'Service exposed to the internet' => 'Reachable by anyone, so by attackers too.',
            'Lack of awareness and training' => 'People do not recognise threats or know the rules.',
            'Undocumented procedures' => 'Operation and recovery depend on what a few people remember.',
            'Dependency on a single supplier' => 'No alternative if the supplier fails.',
            'Expired or unmanaged certificates' => 'Certificates expire unnoticed and break services or trust.',
            'Missing change control' => 'Changes made without review, test or rollback plan.',
            'Inadequate physical protection' => 'Rooms or equipment reachable by unauthorized people.',
        ],
    ],
];
