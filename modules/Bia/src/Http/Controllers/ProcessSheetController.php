<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Http\Controllers;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\View\View;
use Modules\Bia\Domain\Enums\AssessmentStatus;
use Modules\Bia\Domain\Models\Assessment;
use Modules\Bia\Domain\Models\BusinessProcess;
use Modules\Bia\Domain\Models\Dependency;
use Modules\Bia\Domain\Models\ImpactCategory;
use Modules\Bia\Domain\Models\TimeHorizon;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * A process's BIA on one page, to print or save as PDF for an audit: the version
 * in force (or the latest when none is approved yet), with its matrix,
 * objectives, dependencies and approval.
 */
final readonly class ProcessSheetController
{
    public function __construct(
        private Gate $gate,
        private ConfigurationItems $items,
        private OrganizationContext $context,
    ) {}

    public function __invoke(BusinessProcess $process): View
    {
        $this->gate->authorize('view', $process);
        $process->load(['department', 'owner', 'deputy']);

        $assessment = Assessment::query()->with(['submitter', 'decider'])->where('process_id', $process->id)->where('status', AssessmentStatus::Approved->value)->first()
            ?? Assessment::query()->with(['submitter', 'decider'])->where('process_id', $process->id)->orderByDesc('version')->first();

        $scores = $assessment?->scores()->get() ?? collect();

        return view('bia::sheet', [
            'process' => $process,
            'assessment' => $assessment,
            'organization' => Organization::query()->find($this->context->requireId()),
            'categories' => ImpactCategory::query()->withTrashed()->whereKey($scores->pluck('category_id')->unique()->all())->orderBy('sort_order')->orderBy('name')->get(),
            'horizons' => TimeHorizon::query()->withTrashed()->whereKey($scores->pluck('horizon_id')->unique()->all())->orderBy('minutes')->get(),
            'scores' => $assessment?->scoreMap() ?? [],
            'items' => $assessment !== null ? $this->items->linkedTo($assessment) : [],
            'dependencies' => $assessment !== null ? Dependency::query()->with(['supplier', 'location', 'upstream', 'person'])->where('assessment_id', $assessment->id)->get() : collect(),
            'timezone' => config()->string('app.display_timezone'),
        ]);
    }
}
