<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Domain\Workflow;

use Illuminate\Database\ConnectionInterface;
use Modules\Ticketing\Domain\Enums\Impact;
use Modules\Ticketing\Domain\Enums\Priority;
use Modules\Ticketing\Domain\Enums\Urgency;

/**
 * Priority from impact × urgency. Organizations may override single cells
 * (ticketing_priority_matrix); the rest falls back to the default below.
 */
final readonly class PriorityMatrix
{
    /** @var array<string, array<string, Priority>> impact → urgency → priority */
    private const array DEFAULT = [
        'high' => ['high' => Priority::Critical, 'medium' => Priority::High, 'low' => Priority::Medium],
        'medium' => ['high' => Priority::High, 'medium' => Priority::Medium, 'low' => Priority::Low],
        'low' => ['high' => Priority::Medium, 'medium' => Priority::Low, 'low' => Priority::Low],
    ];

    public function __construct(private ConnectionInterface $db) {}

    public static function defaultFor(Impact $impact, Urgency $urgency): Priority
    {
        return self::DEFAULT[$impact->value][$urgency->value];
    }

    public function resolve(string $organizationId, Impact $impact, Urgency $urgency): Priority
    {
        $override = $this->db->table('ticketing_priority_matrix')
            ->where('organization_id', $organizationId)
            ->where('impact', $impact->value)
            ->where('urgency', $urgency->value)
            ->value('priority');

        return is_string($override) ? Priority::from($override) : self::defaultFor($impact, $urgency);
    }
}
