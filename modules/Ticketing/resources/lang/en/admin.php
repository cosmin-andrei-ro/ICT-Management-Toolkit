<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'navigation_group' => 'Service desk',
    'name' => 'Name',
    'description' => 'Description',
    'active' => 'Active',
    'sort_order' => 'Order',
    'save' => 'Save',
    'saved' => 'Saved.',
    'categories' => [
        'label' => 'Category',
        'plural_label' => 'Categories',
        'parent' => 'Parent category',
    ],
    'queues' => [
        'label' => 'Queue',
        'plural_label' => 'Queues',
        'team' => 'Team',
        'team_help' => 'Only members of this team can be assigned tickets in this queue.',
        'default' => 'Default queue',
        'default_help' => 'New tickets without a queue land here. Only one queue can be the default.',
    ],
    'services' => [
        'label' => 'Service',
        'plural_label' => 'Service catalog',
        'owner' => 'Service owner',
        'queue_help' => 'Requests for this service go to this queue; empty means the default queue.',
        'form' => 'Request form',
        'form_help' => 'Questions asked when someone requests this service. Answers are kept with each request, so editing the form later does not change past requests.',
    ],
    'cab_boards' => [
        'label' => 'CAB board',
        'plural_label' => 'CAB boards',
        'members' => 'Members',
        'members_help' => 'Every member except the requester must approve each change. Only members with the "approve changes" permission (CAB member or team lead role) are asked.',
        'emergency' => 'Emergency CAB (ECAB)',
        'emergency_help' => 'Authorizes emergency changes only, such as a critical security patch. Keep it small so it can decide quickly. Regular boards authorize normal changes.',
    ],
    'change_templates' => [
        'label' => 'Standard change template',
        'plural_label' => 'Standard changes',
        'description_help' => 'When this routine change is appropriate. Agents read it before using the template.',
        'active_help' => 'Inactive templates cannot be used for new changes.',
    ],
    'sla_targets' => [
        'title' => 'SLA targets',
        'response' => 'First response',
        'resolution' => 'Resolution',
        'minutes' => 'working minutes',
        'help' => 'Working minutes, counted with the organization\'s default business calendar (Monday–Friday 09:00–17:00 with Romanian public holidays unless configured). 480 minutes = one working day. Changes apply to new or re-prioritized tickets.',
    ],
    'priority_matrix' => [
        'title' => 'Priority matrix',
        'impact' => 'Impact: :impact',
        'urgency' => 'Urgency: :urgency',
    ],
];
