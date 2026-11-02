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
        2 => 'Data Flows & Functionalities',
        3 => 'User Management & Roles',
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
        '2_1' => [
            'title' => 'Data Categories',
            'guidance' => 'For every data category the application stores, processes or transmits, tick the data elements that apply. Then set the overall Data Classification to reflect the most sensitive category. Use Other for anything not listed.',
        ],
        '2_2' => [
            'title' => 'Data Flow Diagram',
            'guidance' => 'Shows where data comes from, where it goes and what is exchanged. The diagram below is drawn from 2.4 System Integrations; describe each network connection (ports, protocol) in 4.3 Firewall Rules & Ports. You can also attach your own diagram (Yourdon / DeMarco notation, level 0 and level 1; level 2 for critical applications) with its .drawio file.',
        ],
        '2_3' => [
            'title' => 'Application Functionalities',
            'guidance' => 'List the main functionalities grouped by module (functional area), one row per functionality, with the same module and menu names as in the application: they are reused in the role permissions matrix (3.4). Note the main data the user enters or the system receives (Input) and what it produces (Output).',
        ],
        '2_4' => [
            'title' => 'System Integrations',
            'guidance' => 'One row per automated data exchange with another system (API call, file transfer, message queue, database link) - not user interfaces or screens. Describe what is exchanged; the network side of each integration (ports, protocol) goes in 4.3 Firewall Rules & Ports.',
        ],
        '3_1' => [
            'title' => 'Authentication & Identity',
            'guidance' => 'Explains how users authenticate, how access is requested and what each role can do.',
        ],
        '3_2_1' => [
            'title' => 'Access Request Procedure - Application Users',
            'guidance' => 'Describe step by step how access is obtained (which form or tool, which role, who approves, typical lead time) for the business users of the application.',
        ],
        '3_2_2' => [
            'title' => 'Access Request Procedure - Administrative & Technical Users',
            'guidance' => 'The same for application administrators, privileged OS / database access and technical accounts (listed in 7 Technical Users / Service Accounts).',
        ],
        '3_3' => [
            'title' => 'Application Roles',
        ],
        '3_4' => [
            'title' => 'Role Permissions Matrix',
            'guidance' => 'Shows which role can access which module, menu and functionality: the rows are the functionalities of 2.3, the columns the roles of 3.3. Tick the box wherever the role has access.',
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
        '2_1' => [
            'classification' => 'Data classification',
            'categories' => 'Data categories',
        ],
        '2_2' => [
            'diagram' => 'Data flow diagram',
        ],
        '2_3' => [
            'functionalities' => [
                'module' => 'Module',
                'menu' => 'Menu / Screen',
                'functionality' => 'Functionality',
                'description' => 'Description',
                'input' => 'Input',
                'output' => 'Output',
            ],
        ],
        '2_4' => [
            'integrations' => [
                'name' => 'Integration',
                'system' => 'System',
                'direction' => 'Direction',
                'type' => 'Type',
                'data' => 'Data / Format',
                'frequency' => 'Frequency',
                'owner' => 'Owner',
            ],
        ],
        '3_1' => [
            'ad_integrated' => 'AD integrated',
            'protocol' => 'Authentication protocol',
            'ad_domain' => 'AD domain / LDAP server',
            'iam_integrated' => 'IAM integrated',
            'iam_resource' => 'IAM resource / entitlement',
            'mfa' => 'Multifactor authentication',
            'mfa_method' => 'MFA method',
            'sso' => 'Single sign-on (SSO)',
        ],
        '3_2_1' => [
            'procedure' => 'Procedure',
        ],
        '3_2_2' => [
            'procedure' => 'Procedure',
        ],
        '3_3' => [
            'roles' => [
                'name' => 'Role name',
                'description' => 'Description',
                'group' => 'AD group / IAM entitlement',
            ],
        ],
        '3_4' => [
            'matrix' => 'Permissions',
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
        '2_3' => [
            'functionalities' => [
                'module' => 'e.g. Tickets',
                'menu' => 'e.g. New Ticket',
                'functionality' => 'e.g. Create ticket',
                'description' => 'e.g. Employee or agent logs an incident or service request',
                'input' => 'e.g. Requester, category, priority, description',
                'output' => 'e.g. Ticket number, confirmation e-mail',
            ],
        ],
        '2_4' => [
            'integrations' => [
                'name' => 'e.g. Import users and groups',
                'system' => 'e.g. Active Directory',
                'data' => 'e.g. User accounts and AD groups, LDAPS',
                'owner' => 'e.g. IAM Team',
            ],
        ],
        '3_1' => [
            'ad_domain' => 'e.g. corp.company.example / ldaps://dc01.corp.company.example',
            'iam_resource' => 'e.g. TKT01_APP',
            'mfa_method' => 'e.g. Microsoft Authenticator, token',
        ],
        '3_2_1' => [
            'procedure' => 'e.g. Create a request in the Ticketing Platform, Identity & Access Management (IAM) Platform or Access Request Portal',
        ],
        '3_2_2' => [
            'procedure' => 'e.g. Request privileged access through the Privileged Access Management (PAM) platform, the IAM platform or the Access Request Portal, approved by the application owner and the Security Team.',
        ],
        '3_3' => [
            'roles' => [
                'name' => 'e.g. Agent',
                'description' => 'e.g. Support agent who works on assigned tickets',
                'group' => 'e.g. SD_Agents',
            ],
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
        'more_chapters' => 'The other chapters of the template (network, infrastructure, software, security, continuity, operations) are added in the next releases.',
        'dfd_title' => 'Data flow diagram of :application',
        'dfd_caption' => 'Drawn from 2.4 System Integrations: systems sending data on the left, systems receiving it on the right, exchanges both ways below.',
        'dfd_empty' => 'Add the system integrations in 2.4 and the data flow diagram is drawn here.',
        'own_diagram' => 'Your own diagram',
        'own_diagram_alt' => 'Diagram attached to :section',
        'download_source' => 'Download the source file (:name)',
        'remove_image' => 'Remove the image',
        'remove_source' => 'Remove the source file',
        'image_file' => 'Image (PNG, JPEG, WebP or GIF)',
        'source_file' => 'Source file (.drawio)',
        'attach' => 'Attach',
        'uploading' => 'Uploading…',
        'matrix_empty' => 'List the functionalities in 2.3 and the roles in 3.3 (then save) to fill in the matrix.',
        'matrix_help' => 'Added or renamed roles appear in the matrix once saved.',
        'matrix_cell' => ':role may use :functionality',
        'granted' => 'Allowed',
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
        'image_type' => 'Choose a PNG, JPEG, WebP or GIF image.',
    ],
];
