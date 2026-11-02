<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Http\Controllers;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\View\View;
use Modules\Bcm\Application\PlanTiming;
use Modules\Bcm\Domain\Enums\VersionStatus;
use Modules\Bcm\Domain\Models\Plan;
use Modules\Bcm\Domain\Models\PlanVersion;
use Modules\Core\Domain\Links\BusinessProcesses;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * The version in force of a plan on paper: the copy people reach for when the
 * application itself may be down. Print it or save it as PDF.
 */
final readonly class PlanSheetController
{
    public function __construct(
        private Gate $gate,
        private BusinessProcesses $processes,
        private ConfigurationItems $items,
        private PlanTiming $timing,
        private OrganizationContext $context,
    ) {}

    public function __invoke(Plan $plan): View
    {
        $this->gate->authorize('view', $plan);
        $plan->load(['owner', 'deputy']);
        $version = PlanVersion::query()->with(['decider'])->where('plan_id', $plan->id)->where('status', VersionStatus::Approved->value)->firstOrFail();
        $items = $this->items->linkedTo($version);

        return view('bcm::sheet', [
            'plan' => $plan,
            'version' => $version,
            'organization' => Organization::query()->find($this->context->requireId()),
            'processes' => array_values($this->processes->find($version->processIds())),
            'items' => $items,
            'itemNames' => collect($items)->pluck('name', 'id')->all(),
            'team' => $version->team()->with('user')->get(),
            'steps' => $version->steps()->with('responsibleUser')->get(),
            'estimate' => $version->estimatedRecoveryMinutes(),
            'strictest' => $this->timing->strictestRto($version),
            'timezone' => config()->string('app.display_timezone'),
        ]);
    }
}
