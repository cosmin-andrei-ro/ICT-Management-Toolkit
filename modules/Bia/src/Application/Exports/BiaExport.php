<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Application\Exports;

use Generator;
use Modules\Bia\Application\Gaps\GapAnalysis;
use Modules\Bia\Domain\Enums\AssessmentStatus;
use Modules\Bia\Domain\Models\Assessment;
use Modules\Bia\Domain\Models\BusinessProcess;
use Modules\Bia\Domain\Models\Dependency;
use Modules\Core\Domain\Exports\ExportSheet;
use Modules\Core\Domain\Exports\ExportSource;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Models\Export;

/**
 * The BIA register for auditors and continuity planning: one row per process
 * with the objectives in force, the dependencies of the approved versions, and
 * the recovery gaps. Durations are in minutes, so they sort and compute.
 */
final readonly class BiaExport implements ExportSource
{
    public const string TYPE = 'bia.register';

    public function __construct(
        private GapAnalysis $gaps,
        private ConfigurationItems $items,
    ) {}

    public function sheets(Export $export): array
    {
        return [
            new ExportSheet(__('bia::export.processes'), [
                __('bia::processes.fields.name'), __('bia::processes.fields.department'), __('bia::processes.fields.owner'), __('bia::processes.fields.deputy'),
                __('bia::processes.fields.status'), __('bia::processes.fields.tier'), __('bia::export.mtpd'), __('bia::export.rto'), __('bia::export.rpo'),
                __('bia::export.version'), __('bia::processes.fields.approved_at'), __('bia::export.approved_by'), __('bia::processes.fields.next_review'), __('bia::export.in_progress'),
            ], $this->processRows()),
            new ExportSheet(__('bia::export.dependencies'), [__('bia::processes.fields.name'), __('bia::export.kind'), __('bia::export.dependency'), __('bia::export.minimum_people')], $this->dependencyRows()),
            new ExportSheet(__('bia::export.gaps'), [
                __('bia::gaps.item'), __('bia::export.status'), __('bia::export.required_rto'), __('bia::export.required_rpo'),
                __('bia::export.capability_rto'), __('bia::export.capability_rpo'), __('bia::gaps.criticality'), __('bia::export.suggested'), __('bia::gaps.processes'),
            ], $this->gapRows()),
        ];
    }

    public function filename(Export $export): string
    {
        return 'bia-'.$export->created_at?->format('Y-m-d');
    }

    /**
     * @return Generator<list<string|int|float|bool|null>>
     */
    private function processRows(): Generator
    {
        $query = BusinessProcess::query()->with(['department', 'owner', 'deputy'])->orderBy('name');

        foreach ($query->lazyById(200) as $process) {
            $approved = Assessment::query()->with('decider')->where('process_id', $process->id)->where('status', AssessmentStatus::Approved->value)->first();
            $open = Assessment::query()->where('process_id', $process->id)->whereIn('status', ['draft', 'submitted', 'returned'])->first();

            yield [
                $process->name,
                $process->department?->name,
                $process->owner?->name,
                $process->deputy?->name,
                __('bia::processes.statuses.'.$process->status),
                $process->tier?->value,
                $process->mtpd_minutes,
                $process->rto_minutes,
                $process->rpo_minutes,
                $approved?->version,
                $process->approved_at?->toDateString(),
                $approved?->decider?->name,
                $process->next_review_on?->toDateString(),
                $open?->status->label(),
            ];
        }
    }

    /**
     * Dependencies of the versions in force.
     *
     * @return Generator<list<string|int|float|bool|null>>
     */
    private function dependencyRows(): Generator
    {
        $approved = Assessment::query()->with('process')->where('status', AssessmentStatus::Approved->value)->get()->keyBy('id');
        $linked = $this->items->linkedIds((new Assessment)->getMorphClass(), array_values($approved->map(fn (Assessment $assessment): string => $assessment->id)->all()));
        $profiles = $this->items->recoveryProfiles(array_values(array_unique(array_merge([], ...array_values($linked)))));

        foreach ($linked as $assessmentId => $itemIds) {
            foreach ($itemIds as $itemId) {
                yield [$approved->get($assessmentId)?->process->name, __('bia::dependencies.systems'), $profiles[$itemId]->name ?? $itemId, null];
            }
        }

        $dependencies = Dependency::query()->with(['supplier', 'location', 'upstream', 'person'])->whereIn('assessment_id', $approved->keys()->all())->orderBy('assessment_id')->get();

        foreach ($dependencies as $dependency) {
            yield [$approved->get($dependency->assessment_id)?->process->name, $dependency->kind->label(), $dependency->label(), $dependency->minimum_people];
        }
    }

    /**
     * @return Generator<list<string|int|float|bool|null>>
     */
    private function gapRows(): Generator
    {
        foreach ($this->gaps->rows() as $row) {
            yield [
                $row->item->name,
                __('bia::gaps.statuses.'.$row->status()),
                $row->requiredRto,
                $row->requiredRpo,
                $row->item->recoveryTimeMinutes,
                $row->item->recoveryPointMinutes,
                $row->item->criticality,
                $row->suggestedCriticality,
                implode(', ', array_map(fn (array $process): string => $process['name'], $row->processes)),
            ];
        }
    }
}
