<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use Modules\Bcm\Domain\Enums\VersionStatus;
use Modules\Bcm\Domain\Models\Plan;
use Modules\Bcm\Domain\Models\PlanVersion;
use Modules\Bcm\Domain\Models\Step;
use Modules\Bcm\Domain\Models\TeamMember;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Models\User;

/**
 * Opens the next version of a plan, or returns the one already open. A new
 * version starts as a copy of the approved one (text, scope, team, runbook), so
 * a review changes only what changed.
 */
final readonly class StartPlanVersion
{
    public function __construct(
        private ConnectionInterface $db,
        private ConfigurationItems $items,
    ) {}

    public function handle(Plan $plan, User $actor): PlanVersion
    {
        return $this->db->transaction(function () use ($plan, $actor): PlanVersion {
            // Locked: two people opening the next version at once get the same one.
            $plan = Plan::query()->lockForUpdate()->findOrFail($plan->id);
            $open = PlanVersion::query()->where('plan_id', $plan->id)->whereIn('status', ['draft', 'submitted', 'returned'])->first();

            if ($open !== null) {
                return $open;
            }

            $approved = PlanVersion::query()->where('plan_id', $plan->id)->where('status', VersionStatus::Approved->value)->first();
            $latest = PlanVersion::query()->where('plan_id', $plan->id)->max('version');

            $version = new PlanVersion;
            $version->forceFill([
                'organization_id' => $plan->organization_id,
                'plan_id' => $plan->id,
                'version' => (is_numeric($latest) ? (int) $latest : 0) + 1,
                'status' => VersionStatus::Draft,
                'objective' => $approved?->objective,
                'scenarios' => $approved !== null ? $approved->scenarios : [],
                'activation_criteria' => $approved?->activation_criteria,
                'activation_authority' => $approved?->activation_authority,
                'communication' => $approved?->communication,
                'strategy' => $approved?->strategy,
                'assumptions' => $approved?->assumptions,
            ])->save();

            if ($approved !== null) {
                $this->copy($approved, $version, $actor);
            }

            return $version;
        });
    }

    private function copy(PlanVersion $from, PlanVersion $to, User $actor): void
    {
        $this->db->table('bcm_version_processes')->insert(array_map(
            fn (string $processId): array => ['version_id' => $to->id, 'organization_id' => $to->organization_id, 'process_id' => $processId],
            $from->processIds(),
        ));

        foreach ($this->items->linkedIds($from->getMorphClass(), [$from->id])[$from->id] ?? [] as $itemId) {
            $this->items->link($to, $itemId, $actor);
        }

        foreach (TeamMember::query()->where('version_id', $from->id)->get() as $member) {
            $member->replicate(['id'])->forceFill(['version_id' => $to->id])->save();
        }

        foreach (Step::query()->where('version_id', $from->id)->get() as $step) {
            $step->replicate(['id'])->forceFill(['version_id' => $to->id])->save();
        }
    }
}
