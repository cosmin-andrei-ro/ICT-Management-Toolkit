<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Application\Exports;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Generator;
use Illuminate\Contracts\Config\Repository as Config;
use Modules\Bcm\Application\Coverage\ContinuityCoverage;
use Modules\Bcm\Application\Coverage\PlanStanding;
use Modules\Bcm\Domain\Models\Activation;
use Modules\Bcm\Domain\Models\Exercise;
use Modules\Bcm\Domain\Models\Finding;
use Modules\Core\Domain\Exports\ExportSheet;
use Modules\Core\Domain\Exports\ExportSource;
use Modules\Core\Domain\Models\Export;

/**
 * The continuity register for auditors: plans and where they stand, the
 * coverage of assessed processes, exercises, their findings and the real
 * activations. Durations are in minutes, so they sort and compute; crisis team
 * contacts stay out of it.
 */
final readonly class BcmExport implements ExportSource
{
    public const string TYPE = 'bcm.register';

    public function __construct(
        private ContinuityCoverage $coverage,
        private Config $config,
    ) {}

    public function sheets(Export $export): array
    {
        $plans = $this->coverage->plans(CarbonImmutable::today());

        return [
            new ExportSheet(__('bcm::export.plans'), [
                __('bcm::plans.fields.name'), __('bcm::plans.fields.type'), __('bcm::plans.fields.owner'), __('bcm::plans.fields.deputy'),
                __('bcm::plans.fields.approved_version'), __('bcm::export.approved_at'), __('bcm::plans.fields.next_review'), __('bcm::export.review_overdue'),
                __('bcm::export.estimate'), __('bcm::export.rto'), __('bcm::export.fits_rto'),
                __('bcm::exercises.fields.last_exercised'), __('bcm::export.exercise_due'), __('bcm::export.last_outcome'), __('bcm::export.awaiting_approval'),
            ], $this->planRows($plans)),
            new ExportSheet(__('bcm::export.coverage'), [
                __('bcm::coverage.process'), __('bcm::export.tier'), __('bcm::export.rto'), __('bcm::coverage.plans'), __('bcm::export.fits_rto'), __('bcm::coverage.tested'),
            ], $this->coverageRows($plans)),
            new ExportSheet(__('bcm::export.exercises'), [
                __('bcm::plans.fields.name'), __('bcm::exercises.fields.title'), __('bcm::exercises.fields.type'), __('bcm::exercises.fields.scenario'), __('bcm::exercises.fields.scheduled_on'),
                __('bcm::export.status'), __('bcm::exercises.fields.outcome'), __('bcm::export.measured'), __('bcm::export.rto'), __('bcm::exercises.fields.facilitator'), __('bcm::exercises.fields.lessons'),
            ], $this->exerciseRows()),
            new ExportSheet(__('bcm::export.findings'), [
                __('bcm::plans.fields.name'), __('bcm::exercises.fields.title'), __('bcm::exercises.fields.finding'), __('bcm::exercises.fields.severity'), __('bcm::export.ticket'),
            ], $this->findingRows()),
            new ExportSheet(__('bcm::export.activations'), [
                __('bcm::plans.fields.name'), __('bcm::activations.fields.activated_at'), __('bcm::exercises.fields.scenario'), __('bcm::activations.fields.reason'),
                __('bcm::activations.fields.restored_at'), __('bcm::export.measured'), __('bcm::export.rto'), __('bcm::export.status'), __('bcm::exercises.fields.lessons'),
            ], $this->activationRows()),
        ];
    }

    public function filename(Export $export): string
    {
        return 'continuity-'.$export->created_at?->format('Y-m-d');
    }

    /**
     * @param  list<PlanStanding>  $plans
     * @return Generator<list<string|int|float|bool|null>>
     */
    private function planRows(array $plans): Generator
    {
        foreach ($plans as $standing) {
            $plan = $standing->plan;

            yield [
                $plan->name,
                $plan->type->label(),
                $plan->owner?->name,
                $plan->deputy?->name,
                $plan->approved_version,
                $plan->approved_at?->toDateString(),
                $plan->next_review_on?->toDateString(),
                $standing->reviewOverdue,
                $standing->estimateMinutes,
                $standing->rtoMinutes,
                $standing->fitsRto(),
                $plan->last_exercised_on?->toDateString(),
                $standing->exerciseDueOn?->toDateString(),
                $standing->lastOutcome,
                $standing->awaitingApproval,
            ];
        }
    }

    /**
     * @param  list<PlanStanding>  $plans
     * @return Generator<list<string|int|float|bool|null>>
     */
    private function coverageRows(array $plans): Generator
    {
        foreach ($this->coverage->processes($plans) as $coverage) {
            yield [
                $coverage->process->name,
                $coverage->process->tier,
                $coverage->process->rtoMinutes,
                implode(', ', array_map(fn (PlanStanding $standing): string => $standing->plan->name, $coverage->plans)),
                $coverage->fitsRto(),
                $coverage->isTested(),
            ];
        }
    }

    /**
     * @return Generator<list<string|int|float|bool|null>>
     */
    private function exerciseRows(): Generator
    {
        foreach (Exercise::query()->with(['plan', 'facilitator'])->lazyById(200) as $exercise) {
            yield [
                $exercise->plan->name,
                $exercise->title,
                $exercise->type->label(),
                $exercise->scenario?->label(),
                $exercise->scheduled_on->toDateString(),
                $exercise->status->label(),
                $exercise->outcome?->label(),
                $exercise->measured_minutes,
                $exercise->rto_minutes,
                $exercise->facilitator?->name,
                $exercise->lessons,
            ];
        }
    }

    /**
     * @return Generator<list<string|int|float|bool|null>>
     */
    private function findingRows(): Generator
    {
        $exercises = Exercise::query()->with('plan')->get(['id', 'plan_id', 'title'])->keyBy('id');

        foreach (Finding::query()->lazyById(200) as $finding) {
            $exercise = $exercises->get($finding->exercise_id);

            yield [$exercise?->plan->name, $exercise?->title, $finding->description, $finding->severity->label(), $finding->ticket_number];
        }
    }

    /**
     * @return Generator<list<string|int|float|bool|null>>
     */
    private function activationRows(): Generator
    {
        foreach (Activation::query()->with('plan')->lazyById(200) as $activation) {
            yield [
                $activation->plan->name,
                $this->local($activation->activated_at),
                $activation->scenario?->label(),
                $activation->reason,
                $this->local($activation->restored_at),
                $activation->recoveryMinutes(),
                $activation->rto_minutes,
                $activation->isOpen() ? __('bcm::activations.open') : __('bcm::activations.closed'),
                $activation->lessons,
            ];
        }
    }

    private function local(?CarbonInterface $instant): ?string
    {
        $timezone = $this->config->get('app.display_timezone');

        return $instant?->copy()->setTimezone(is_string($timezone) && $timezone !== '' ? $timezone : 'UTC')->format('Y-m-d H:i');
    }
}
