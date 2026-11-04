<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Models\Organization;
use Modules\Ticketing\Domain\Enums\Impact;
use Modules\Ticketing\Domain\Enums\Priority;
use Modules\Ticketing\Domain\Enums\Urgency;
use Modules\Ticketing\Domain\Workflow\PriorityMatrix;

/**
 * Stores only the cells that differ from the default. Applies to tickets
 * created or triaged afterwards; existing priorities are not rewritten.
 */
final readonly class SavePriorityMatrix
{
    public function __construct(
        private ConnectionInterface $db,
        private PriorityMatrix $matrix,
        private AuditTrail $trail,
    ) {}

    /**
     * @return array<string, string> "impact.urgency" => priority, for every cell.
     */
    public function current(string $organizationId): array
    {
        $cells = [];

        foreach (Impact::cases() as $impact) {
            foreach (Urgency::cases() as $urgency) {
                $cells["{$impact->value}.{$urgency->value}"] = $this->matrix->resolve($organizationId, $impact, $urgency)->value;
            }
        }

        return $cells;
    }

    /**
     * @param  array<string, Priority>  $cells  "impact.urgency" => priority, for every cell.
     */
    public function handle(Organization $organization, array $cells): void
    {
        $before = $this->current($organization->id);

        $this->db->transaction(function () use ($organization, $cells): void {
            foreach (Impact::cases() as $impact) {
                foreach (Urgency::cases() as $urgency) {
                    $priority = $cells["{$impact->value}.{$urgency->value}"] ?? PriorityMatrix::defaultFor($impact, $urgency);
                    $key = ['organization_id' => $organization->id, 'impact' => $impact->value, 'urgency' => $urgency->value];

                    if ($priority === PriorityMatrix::defaultFor($impact, $urgency)) {
                        $this->db->table('ticketing_priority_matrix')->where($key)->delete();

                        continue;
                    }

                    $this->db->table('ticketing_priority_matrix')->updateOrInsert($key, [
                        'priority' => $priority->value,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]);
                }
            }
        });

        $after = $this->current($organization->id);

        if ($before !== $after) {
            $this->trail->record(AuditEvent::Updated, $organization, ['priority_matrix' => $before], ['priority_matrix' => $after]);
        }
    }
}
