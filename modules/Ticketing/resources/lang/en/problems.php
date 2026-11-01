<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'workaround_note' => "Linked to known error :number. Workaround:\n:workaround",
    'resolved_by_problem' => "Resolved together with problem :number.\n:note",
    'ui' => [
        'problem' => 'Problem',
        'analysis' => 'Analysis',
        'root_cause' => 'Root cause',
        'workaround' => 'Workaround',
        'known_error' => 'Known error',
        'mark_known_error' => 'Mark as known error',
        'linked_incidents' => 'Linked incidents (:count)',
        'no_incidents' => 'No incidents linked yet.',
        'link' => 'Link',
        'unlink' => 'Unlink',
        'create_from_incident' => 'Open a problem from this incident',
        'resolve_incidents' => 'Also resolve the :count open linked incident(s)',
        'known_errors' => 'Known errors',
        'known_errors_intro' => 'Problems with a documented workaround. Apply the workaround, then link the incident to the problem.',
        'search_placeholder' => 'Symptoms, error text…',
        'empty' => 'No known errors match.',
        'incident_count' => '{0} no incidents|{1} :count incident|[2,*] :count incidents',
    ],
];
