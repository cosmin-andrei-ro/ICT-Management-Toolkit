<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Models\Organization;
use Modules\Ticketing\Domain\Enums\Priority;
use Modules\Ticketing\Domain\Sla\SlaTarget;
use Modules\Ticketing\Domain\Sla\SlaTargets;

/**
 * Stores only targets that differ from the defaults. New targets apply to tickets
 * created or re-prioritized afterwards; running clocks are not rewritten.
 */
final readonly class SaveSlaTargets
{
    public function __construct(
        private ConnectionInterface $db,
        private SlaTargets $targets,
        private AuditTrail $trail,
    ) {}

    /**
     * @return array<string, array{response: int, resolution: int}>
     */
    public function current(string $organizationId): array
    {
        $current = [];

        foreach (Priority::cases() as $priority) {
            $target = $this->targets->resolve($organizationId, $priority);
            $current[$priority->value] = ['response' => $target->responseMinutes, 'resolution' => $target->resolutionMinutes];
        }

        return $current;
    }

    /**
     * @param  array<string, SlaTarget>  $targets  priority => target, for every priority.
     */
    public function handle(Organization $organization, array $targets): void
    {
        foreach ($targets as $target) {
            if ($target->responseMinutes < 1 || $target->resolutionMinutes < $target->responseMinutes) {
                throw new InvalidArgumentException('Response must be at least one minute and resolution no shorter than response.');
            }
        }

        $before = $this->current($organization->id);

        $this->db->transaction(function () use ($organization, $targets): void {
            foreach (Priority::cases() as $priority) {
                $target = $targets[$priority->value] ?? SlaTargets::defaultFor($priority);
                $key = ['organization_id' => $organization->id, 'priority' => $priority->value];

                if ($target->equals(SlaTargets::defaultFor($priority))) {
                    $this->db->table('ticketing_sla_targets')->where($key)->delete();

                    continue;
                }

                $this->db->table('ticketing_sla_targets')->updateOrInsert($key, [
                    'response_minutes' => $target->responseMinutes,
                    'resolution_minutes' => $target->resolutionMinutes,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $after = $this->current($organization->id);

        if ($before !== $after) {
            $this->trail->record(AuditEvent::Updated, $organization, ['sla_targets' => $before], ['sla_targets' => $after]);
        }
    }
}
