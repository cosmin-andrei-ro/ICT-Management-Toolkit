<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Audit\Domain\Enums\AuditStatus;
use Modules\Audit\Domain\Models\Audit;

/**
 * Internal and external audits, latest first.
 */
#[Layout('core::components.layouts.app')]
final class AuditIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $status = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Audit::class);
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $this->authorize('viewAny', Audit::class);
        $perPage = config('audit.compliance.per_page');

        return view('audit::livewire.audits.index', [
            'audits' => Audit::query()
                ->with('leadAuditor')
                ->withCount(['findings', 'findings as open_findings_count' => fn (Builder $query) => $query->where('status', '!=', 'closed')])
                ->when(AuditStatus::tryFrom($this->status) !== null, fn (Builder $query) => $query->where('status', $this->status))
                ->orderByDesc('starts_on')
                ->orderByDesc('number')
                ->paginate(is_int($perPage) ? $perPage : 25),
            'statuses' => AuditStatus::cases(),
        ])->title(__('audit::audits.title'));
    }
}
