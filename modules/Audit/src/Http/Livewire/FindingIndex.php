<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Http\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Audit\Domain\Enums\FindingSeverity;
use Modules\Audit\Domain\Enums\FindingStatus;
use Modules\Audit\Domain\Models\Finding;

/**
 * Every finding, from audits and control tests, with its owner and due date.
 */
#[Layout('core::components.layouts.app')]
final class FindingIndex extends Component
{
    use WithPagination;

    /** open (open or under verification), closed, or empty for all. */
    #[Url]
    public string $status = 'open';

    #[Url]
    public string $severity = '';

    #[Url]
    public bool $overdue = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Finding::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'severity', 'overdue'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $this->authorize('viewAny', Finding::class);
        $today = CarbonImmutable::today();
        $perPage = config('audit.compliance.per_page');

        return view('audit::livewire.findings.index', [
            'findings' => Finding::query()
                ->with(['owner', 'audit', 'control'])
                ->when($this->status === 'open', fn (Builder $query) => $query->where('status', '!=', FindingStatus::Closed->value))
                ->when($this->status === 'closed', fn (Builder $query) => $query->where('status', FindingStatus::Closed->value))
                ->when(FindingSeverity::tryFrom($this->severity) !== null, fn (Builder $query) => $query->where('severity', $this->severity))
                ->when($this->overdue, fn (Builder $query) => $query->where('status', '!=', FindingStatus::Closed->value)->where('due_on', '<', $today->toDateString()))
                ->orderByRaw("CASE severity WHEN 'major_nonconformity' THEN 0 WHEN 'minor_nonconformity' THEN 1 WHEN 'observation' THEN 2 ELSE 3 END")
                ->orderBy('due_on')
                ->orderBy('number')
                ->paginate(is_int($perPage) ? $perPage : 25),
            'severities' => FindingSeverity::cases(),
            'today' => $today,
        ])->title(__('audit::audits.findings.title'));
    }
}
