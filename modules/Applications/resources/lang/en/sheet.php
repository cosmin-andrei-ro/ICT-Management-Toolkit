<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

/*
| Labels, guidance and examples of the application technical documentation,
| from the owner's template (docs/templates/application-technical-documentation.md).
*/

return [
    'title' => 'Technical documentation',
    'chapters' => [
        0 => 'Document control',
        1 => 'General Information',
    ],
    'sections' => [
        'doc' => [
            'title' => 'Document control',
            'guidance' => 'Records who owns this document. The review dates follow the approvals: the next review is due a year after a version comes into force.',
        ],
        '1_1' => [
            'title' => 'Application Description',
            'guidance' => 'In two or three sentences, explain what the application does, who uses it and which business process it supports.',
        ],
        '1_2_1' => [
            'title' => 'Internal Support',
            'guidance' => 'Lists who to contact when something goes wrong - internal teams first, then the vendor.',
        ],
        '1_2_2' => [
            'title' => 'Vendor Support',
        ],
        '1_3' => [
            'title' => 'Service Classification',
        ],
        '1_4' => [
            'title' => 'Environments',
            'guidance' => 'List every environment with its URL, including Production and Test.',
        ],
        '1_5' => [
            'title' => 'Related Documentation & Repositories',
        ],
        '1_6' => [
            'title' => 'Service Levels & Maintenance',
            'guidance' => 'Records the service level agreed with the business and when planned maintenance may take place.',
        ],
    ],
    'fields' => [
        'doc' => [
            'document_owner' => 'Document owner',
            'document_location' => 'Document location (if also kept elsewhere)',
        ],
        '1_1' => [
            'description' => 'Application description',
        ],
        '1_2_1' => [
            'internal_support' => 'Internal support contacts',
        ],
        '1_2_2' => [
            'supplier' => 'Vendor company',
            'contract' => 'Contract',
            'support_portal' => 'Support portal',
            'support_email' => 'Support e-mail',
        ],
        '1_3' => [
            'status' => 'Application status',
            'criticality' => 'Business criticality',
            'exposure' => 'Exposure',
            'cartography' => 'Application cartography profile',
        ],
        '1_4' => [
            'environments' => [
                'environment' => 'Environment',
                'url' => 'URL',
                'purpose' => 'Purpose',
                'access' => 'Access notes',
            ],
        ],
        '1_5' => [
            'documents' => [
                'type' => 'Type',
                'name' => 'Name',
                'link' => 'Link',
            ],
        ],
        '1_6' => [
            'availability' => 'Availability target (SLA)',
            'support_hours' => 'Support hours',
            'response_times' => 'Incident response / resolution times',
            'maintenance_window' => 'Planned maintenance window',
            'change_freeze' => 'Change freeze periods',
            'maintenance_notification' => 'Maintenance notification',
        ],
    ],
    'examples' => [
        'doc' => [
            'document_location' => 'e.g. SharePoint › IT Documentation › Ticketing Platform',
        ],
        '1_1' => [
            'description' => 'e.g. Ticketing Platform is the internal IT support application. Employees and the monitoring system raise tickets, support agents resolve them using a shared knowledge base, and IT managers receive SLA reports.',
        ],
        '1_2_2' => [
            'support_portal' => 'e.g. https://support.example.com',
            'support_email' => 'e.g. support@example.com',
        ],
        '1_4' => [
            'environments' => [
                'url' => 'e.g. https://servicedesk.company.local',
                'purpose' => 'e.g. Live ticket handling for all employees',
                'access' => 'e.g. All employees via SSO; agents via AD group SD_Agents',
            ],
        ],
        '1_5' => [
            'documents' => [
                'name' => 'e.g. Ticketing Platform source code',
                'link' => 'e.g. https://git.company.local/servicedesk/ticketing',
            ],
        ],
        '1_6' => [
            'availability' => 'e.g. 99.5% during business hours',
            'response_times' => 'e.g. P1: 30 min / 4 h; P2: 2 h / 1 day',
            'maintenance_window' => 'e.g. Sunday 02:00-06:00',
            'change_freeze' => 'e.g. Year-end closing, major IT migration weekends',
            'maintenance_notification' => 'e.g. 5 working days in advance, banner in the portal and e-mail to all users',
        ],
    ],
    'choices' => [
        'exposure' => [
            'internal' => 'Internal',
            'internet' => 'Internet-facing',
            'third_party' => 'External 3rd party',
        ],
    ],
    'ui' => [
        'chapters' => 'Chapters',
        'more_chapters' => 'The other chapters of the template (data flows, users and roles, network, infrastructure, software, security, continuity, operations) are added in the next releases.',
        'to_complete' => 'to complete',
        'version' => 'version :version',
        'new_major' => 'new major version',
        'new_minor' => 'new minor version',
        'major' => 'Major change',
        'minor' => 'Minor change',
        'major_help' => 'Architecture, data, security, users or integrations change: the business owner, the IT owner and a security officer approve it.',
        'minor_help' => 'Corrections and small updates: in force at once, logged in the change history.',
        'kind' => 'Kind of change',
        'first_help' => 'The first version describes the application as it is today and is approved by the business owner, the IT owner and a security officer.',
        'change_heading' => 'This version',
        'change_summary' => 'Description of change',
        'change_example_first' => 'e.g. Initial documentation of the existing application',
        'change_example' => 'e.g. Added e-mail-to-ticket integration with Microsoft 365',
        'save' => 'Save',
        'saving' => 'Saving…',
        'submit' => 'Submit for approval',
        'publish' => 'Publish',
        'secrets_warning' => 'Never store passwords, keys or secrets in this document - reference the vault location only.',
        'choose' => 'Choose an option',
        'other' => 'Other…',
        'other_text' => ':field: other value',
        'other_placeholder' => 'Type the value',
        'contact_placeholder' => 'e.g. team@company.example',
        'add_row' => 'Add a row',
        'remove_row' => 'Remove',
        'no_rows' => 'No row yet.',
        'actions' => 'Actions',
        'unknown' => 'No longer available',
        'submitted_by' => 'Submitted by :name on :date.',
        'returned_by' => 'Returned by :name:',
        'approvals' => 'Approvals',
        'approved_by' => 'Approved by :name on :date',
        'waiting' => 'Waiting',
        'approve_as' => 'Approve as',
        'comment' => 'Comment (required to return it)',
        'approve' => 'Approve',
        'send_back' => 'Return for changes',
    ],
    'errors' => [
        'incomplete' => 'Complete these sections first: :sections.',
        'change_summary' => 'Describe what changed in this version.',
        'locked' => 'This version can no longer be changed. Reload the page.',
        'reason' => 'Say why the version is returned.',
        'role' => 'Choose which approval you give.',
        'cannot_approve' => 'You cannot give this approval: nobody approves a version they submitted, and one person gives one approval.',
        'unknown_reference' => 'This entry is not available in your organization.',
    ],
];
