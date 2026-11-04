<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Application\Coverage;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\ConnectionInterface;
use Modules\Bcm\Domain\Enums\ExerciseStatus;
use Modules\Bcm\Domain\Enums\VersionStatus;
use Modules\Bcm\Domain\Models\Activation;
use Modules\Bcm\Domain\Models\Exercise;
use Modules\Bcm\Domain\Models\Finding;
use Modules\Bcm\Domain\Models\Plan;
use Modules\Bcm\Domain\Models\PlanVersion;
use Modules\Bcm\Domain\Models\Step;
use Modules\Core\Domain\Links\BusinessProcesses;

/**
 * How well the current organization's continuity plans cover its business: the
 * standing of every active plan, the assessed processes with and without a
 * plan in force, and a few figures for the overview and the weekly email.
 * A handful of queries, whatever the number of plans.
 */
final readonly class ContinuityCoverage
{
    public function __construct(
        private ConnectionInterface $db,
        private BusinessProcesses $processes,
        private Config $config,
    ) {}

    public function exerciseMonths(): int
    {
        $months = $this->config->get('bcm.plans.exercise_months');

        return is_int($months) && $months > 0 ? $months : 12;
    }

    /**
     * @return list<PlanStanding> By type, then name.
     */
    public function plans(CarbonImmutable $today): array
    {
        $plans = Plan::query()->with(['owner', 'deputy'])->where('status', 'active')->orderBy('type')->orderBy('name')->get();
        $planIds = $plans->modelKeys();
        $versions = PlanVersion::query()->whereIn('plan_id', $planIds)->where('status', VersionStatus::Approved)->get(['id', 'plan_id'])->keyBy('plan_id');
        $versionIds = array_values($versions->map(fn (PlanVersion $version): string => $version->id)->all());
        $submitted = PlanVersion::query()->whereIn('plan_id', $planIds)->where('status', VersionStatus::Submitted)->pluck('plan_id')->flip();

        $estimates = [];

        foreach (Step::query()->whereIn('version_id', $versionIds)->whereIn('phase', ['activation', 'recovery'])->whereNotNull('estimated_minutes')->get(['version_id', 'estimated_minutes']) as $step) {
            $estimates[$step->version_id] = ($estimates[$step->version_id] ?? 0) + (int) $step->estimated_minutes;
        }

        $rtos = $this->strictestRtoByVersion($versionIds);
        $outcomes = [];

        foreach (Exercise::query()->whereIn('plan_id', $planIds)->where('status', ExerciseStatus::Completed)->orderBy('completed_at')->get(['plan_id', 'outcome']) as $exercise) {
            $outcomes[$exercise->plan_id] = $exercise->outcome?->label();
        }

        $months = $this->exerciseMonths();

        return array_values($plans->map(function (Plan $plan) use ($versions, $submitted, $estimates, $rtos, $outcomes, $months, $today): PlanStanding {
            $versionId = $versions->get($plan->id)?->id;
            $exerciseDue = $plan->exerciseDueOn($months);

            return new PlanStanding(
                $plan,
                $versionId,
                $versionId !== null ? ($estimates[$versionId] ?? null) : null,
                $versionId !== null ? ($rtos[$versionId] ?? null) : null,
                $exerciseDue,
                $plan->next_review_on !== null && $plan->next_review_on->lt($today),
                $exerciseDue !== null && $exerciseDue->lt($today),
                $submitted->has($plan->id),
                $outcomes[$plan->id] ?? null,
            );
        })->all());
    }

    /**
     * Every assessed process, strictest RTO first, with the plans in force covering it.
     *
     * @param  list<PlanStanding>  $plans
     * @return list<ProcessCoverage>
     */
    public function processes(array $plans): array
    {
        $byVersion = [];

        foreach ($plans as $standing) {
            if ($standing->versionId !== null) {
                $byVersion[$standing->versionId] = $standing;
            }
        }

        $covering = [];

        foreach ($this->links(array_keys($byVersion)) as [$versionId, $processId]) {
            $covering[$processId][] = $byVersion[$versionId];
        }

        return array_map(
            fn ($process): ProcessCoverage => new ProcessCoverage($process, $covering[$process->id] ?? []),
            $this->processes->assessed(),
        );
    }

    /**
     * @return array{plans: int, not_in_force: int, review_overdue: int, exercise_overdue: int, awaiting_approval: int, processes: int, uncovered: int, open_findings: int, open_activations: int}
     */
    public function figures(CarbonImmutable $today): array
    {
        $plans = $this->plans($today);
        $processes = $this->processes($plans);

        return [
            'plans' => count($plans),
            'not_in_force' => count(array_filter($plans, fn (PlanStanding $standing): bool => ! $standing->inForce())),
            'review_overdue' => count(array_filter($plans, fn (PlanStanding $standing): bool => $standing->reviewOverdue)),
            'exercise_overdue' => count(array_filter($plans, fn (PlanStanding $standing): bool => $standing->exerciseOverdue)),
            'awaiting_approval' => count(array_filter($plans, fn (PlanStanding $standing): bool => $standing->awaitingApproval)),
            'processes' => count($processes),
            'uncovered' => count(array_filter($processes, fn (ProcessCoverage $coverage): bool => ! $coverage->isCovered())),
            // Findings of completed exercises nobody has handed to IT support.
            'open_findings' => Finding::query()->whereNull('ticket_id')->whereIn('exercise_id', Exercise::query()->where('status', ExerciseStatus::Completed)->select('id'))->count(),
            'open_activations' => Activation::query()->whereNull('closed_at')->count(),
        ];
    }

    /**
     * @param  list<string>  $versionIds
     * @return array<string, int>
     */
    private function strictestRtoByVersion(array $versionIds): array
    {
        $links = $this->links($versionIds);
        $found = $this->processes->find(array_values(array_unique(array_column($links, 1))));
        $rtos = [];

        foreach ($links as [$versionId, $processId]) {
            $rto = $found[$processId]->rtoMinutes ?? null;

            if ($rto !== null && (! isset($rtos[$versionId]) || $rto < $rtos[$versionId])) {
                $rtos[$versionId] = $rto;
            }
        }

        return $rtos;
    }

    /**
     * Which processes each version covers.
     *
     * @param  list<string>  $versionIds
     * @return list<array{0: string, 1: string}> Version id, process id.
     */
    private function links(array $versionIds): array
    {
        $links = [];

        foreach ($this->db->table('bcm_version_processes')->whereIn('version_id', $versionIds)->get(['version_id', 'process_id']) as $row) {
            if (is_string($row->version_id) && is_string($row->process_id)) {
                $links[] = [$row->version_id, $row->process_id];
            }
        }

        return $links;
    }
}
