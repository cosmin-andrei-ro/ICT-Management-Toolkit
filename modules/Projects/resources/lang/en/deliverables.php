<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'heading' => 'Deliverables',
    'none' => 'No deliverables yet.',
    'title' => 'Deliverable',
    'add' => 'Add',
    'required' => 'Required to close',
    'optional' => 'Optional',
    'delivered_on' => 'Delivered :date by :name',
    'not_delivered' => 'Not delivered yet',
    'deliver' => 'Mark delivered',
    'undeliver' => 'Not delivered',
    'link' => 'Link a record',
    'unlink' => 'Remove the link',
    'delete_confirm' => 'Delete this deliverable?',
    'kind' => 'Kind of record',
    'choose_kind' => 'Kind of record…',
    'search' => 'Search',
    'blockers' => 'Before the project can close:',
    'not_ready' => 'The project cannot close yet. :reasons',
    'missing' => 'Required deliverables not delivered: :titles.',
    'checklist_heading' => 'New application checklist',
    'checklist_help' => 'Each item is read from its module and updates by itself. The project closes only when all are done.',
    'choose_application' => 'The application this project brings in',
    'change_application' => 'Change the application',
    'application_search' => 'Application name or code',
    'done' => 'done',
    'to_do' => 'to do',
    'items' => [
        'application' => 'The application is recorded in the portfolio',
        'documentation' => 'Its documentation is approved',
        'cmdb' => 'It has its item in the CMDB',
        'risks' => 'Its risks are assessed in the risk register',
        'bia' => 'The business processes it serves are assessed in the BIA, with their RTO',
    ],
    'checks' => [
        'application' => 'Choose the application this project brings in.',
        'documentation' => 'Have the application\'s documentation approved.',
        'cmdb' => 'The application needs its item in the CMDB (created when its documentation is approved).',
        'risks' => 'Assess at least one risk concerning the application in the risk register.',
        'bia' => 'Link the business processes the application serves in its documentation, and have them assessed in the BIA.',
    ],
    'errors' => [
        'record' => 'Choose a record you can see.',
        'not_application' => 'Only a new-application project has an application.',
        'application' => 'Choose an application of the portfolio.',
    ],
];
