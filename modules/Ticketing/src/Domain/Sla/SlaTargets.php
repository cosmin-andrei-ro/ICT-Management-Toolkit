<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Domain\Sla;

use Illuminate\Database\ConnectionInterface;
use Modules\Ticketing\Domain\Enums\Priority;

/**
 * Response and resolution targets per priority, in working minutes. Organizations
 * may override single priorities (ticketing_sla_targets).
 */
final readonly class SlaTargets
{
    /** @var array<string, array{0: int, 1: int}> priority => [response, resolution] */
    private const array DEFAULT = [
        'critical' => [15, 4 * 60],
        'high' => [60, 8 * 60],
        'medium' => [4 * 60, 3 * 8 * 60],
        'low' => [8 * 60, 5 * 8 * 60],
    ];

    public function __construct(private ConnectionInterface $db) {}

    public static function defaultFor(Priority $priority): SlaTarget
    {
        return new SlaTarget(...self::DEFAULT[$priority->value]);
    }

    public function resolve(string $organizationId, Priority $priority): SlaTarget
    {
        $row = $this->db->table('ticketing_sla_targets')
            ->where('organization_id', $organizationId)
            ->where('priority', $priority->value)
            ->first(['response_minutes', 'resolution_minutes']);

        if ($row === null || ! is_numeric($row->response_minutes ?? null) || ! is_numeric($row->resolution_minutes ?? null)) {
            return self::defaultFor($priority);
        }

        return new SlaTarget((int) $row->response_minutes, (int) $row->resolution_minutes);
    }
}
