<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Domain\Models\User;
use Modules\Projects\Domain\Enums\ProjectStatus;
use Modules\Projects\Domain\Enums\Rag;
use Modules\Projects\Domain\Models\Project;
use Modules\Projects\Domain\Models\StatusReport;

/**
 * Every open project at a glance for management: phase, latest status,
 * dates, and gates waiting for a sponsor.
 */
#[Layout('core::components.layouts.app')]
final class Portfolio extends Component
{
    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && ($user->can('projects.project.view') || $user->can('projects.project.manage')), 403);
    }

    public function render(): View
    {
        $projects = Project::query()->with(['manager', 'sponsor', 'phases'])
            ->whereIn('status', [ProjectStatus::Active->value, ProjectStatus::OnHold->value])
            ->withExists(['gateRequests as gate_pending' => fn (Builder $query) => $query->where('status', 'pending')])
            ->orderBy('planned_end')->orderBy('code')
            ->get();
        $latest = StatusReport::query()->whereIn('project_id', $projects->modelKeys())
            ->orderByDesc('reported_on')->get()->unique('project_id')->keyBy('project_id');
        $every = config()->integer('projects.reports.every_days', 14);

        return view('projects::livewire.portfolio', [
            'projects' => $projects,
            'latest' => $latest,
            'every' => $every,
            'counts' => collect(Rag::cases())->mapWithKeys(fn (Rag $rag): array => [$rag->value => $latest->filter(fn (StatusReport $report): bool => $report->overall === $rag)->count()])->all(),
            'unreported' => $projects->count() - $latest->count(),
        ])->title(__('projects::governance.portfolio'));
    }
}
