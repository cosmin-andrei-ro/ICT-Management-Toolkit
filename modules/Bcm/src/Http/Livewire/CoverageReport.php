<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Http\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Bcm\Application\Coverage\ContinuityCoverage;
use Modules\Bcm\Application\Coverage\ProcessCoverage;
use Modules\Bcm\Application\Exports\BcmExport;
use Modules\Bcm\Domain\Models\Plan;
use Modules\Core\Application\Exports\RequestExport;
use Modules\Core\Domain\Exports\ExportFormat;
use Modules\Core\Domain\Exports\ExportStatus;
use Modules\Core\Domain\Links\BusinessProcesses;
use Modules\Core\Domain\Models\Export;
use Modules\Core\Domain\Models\User;

/**
 * How well the plans cover the business: plans to approve, review or exercise,
 * and assessed processes without a plan in force.
 */
#[Layout('core::components.layouts.app')]
final class CoverageReport extends Component
{
    #[Url]
    public bool $gapsOnly = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Plan::class);
    }

    public function export(string $format, RequestExport $request): void
    {
        $this->authorize('viewAny', Plan::class);
        $user = auth()->user();
        $format = ExportFormat::tryFrom($format);

        if (! $user instanceof User || $format === null) {
            return;
        }

        // RequestExport checks the permission and records the export in the audit trail.
        $request->handle($user, BcmExport::TYPE, $format, []);
    }

    public function render(ContinuityCoverage $coverage, BusinessProcesses $processes): View
    {
        $this->authorize('viewAny', Plan::class);
        $today = CarbonImmutable::today();
        $plans = $coverage->plans($today);
        $processRows = $coverage->processes($plans);
        $exports = Export::query()->where('user_id', auth()->id())->where('type', BcmExport::TYPE)->latest()->limit(3)->get();

        return view('bcm::livewire.coverage', [
            'figures' => $coverage->figures($today),
            'plans' => $plans,
            'processRows' => $this->gapsOnly ? array_values(array_filter($processRows, fn (ProcessCoverage $row): bool => ! $row->isCovered() || $row->fitsRto() === false || ! $row->isTested())) : $processRows,
            'processesAvailable' => $processes->isAvailable(),
            'exports' => $exports,
            'preparing' => $exports->contains(fn (Export $export): bool => $export->status === ExportStatus::Pending),
            'timezone' => config()->string('app.display_timezone'),
        ])->title(__('bcm::coverage.title'));
    }
}
