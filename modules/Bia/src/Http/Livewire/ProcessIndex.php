<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Http\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Bia\Application\Actions\ProvisionDefaultMatrix;
use Modules\Bia\Application\Exports\BiaExport;
use Modules\Bia\Application\Reviews\BiaSummary;
use Modules\Bia\Domain\Enums\Tier;
use Modules\Bia\Domain\Models\Assessment;
use Modules\Bia\Domain\Models\BusinessProcess;
use Modules\Core\Application\Exports\RequestExport;
use Modules\Core\Domain\Exports\ExportFormat;
use Modules\Core\Domain\Exports\ExportStatus;
use Modules\Core\Domain\Models\Department;
use Modules\Core\Domain\Models\Export;
use Modules\Core\Domain\Models\User;

#[Layout('core::components.layouts.app')]
final class ProcessIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $department = '';

    #[Url]
    public string $tier = '';

    #[Url]
    public bool $retired = false;

    /** Only processes never approved or past their review date. */
    #[Url]
    public bool $due = false;

    public function mount(ProvisionDefaultMatrix $provision): void
    {
        $this->authorize('viewAny', BusinessProcess::class);
        $provision->handle();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'department', 'tier', 'retired', 'due'], true)) {
            $this->resetPage();
        }
    }

    public function export(string $format, RequestExport $request): void
    {
        $user = auth()->user();
        $format = ExportFormat::tryFrom($format);

        if (! $user instanceof User || $format === null) {
            return;
        }

        // RequestExport checks the permission and records the export in the audit trail.
        $request->handle($user, BiaExport::TYPE, $format, []);
    }

    public function render(BiaSummary $summary): View
    {
        $this->authorize('viewAny', BusinessProcess::class);
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], trim(mb_substr($this->search, 0, 200))).'%';

        $processes = BusinessProcess::query()
            ->with(['department', 'owner'])
            ->when(trim($this->search) !== '', fn ($query) => $query->where(fn ($inner) => $inner->whereLike('name', $like)->orWhereLike('description', $like)))
            ->when(Str::isUuid($this->department), fn ($query) => $query->where('department_id', $this->department))
            ->when(Tier::tryFrom((int) $this->tier) !== null, fn ($query) => $query->where('tier', (int) $this->tier))
            ->when(! $this->retired, fn ($query) => $query->where('status', 'active'))
            ->when($this->due, fn ($query) => $query->where(fn ($inner) => $inner->whereNull('next_review_on')->orWhere('next_review_on', '<=', now()->toDateString())))
            ->orderByRaw('tier ASC NULLS LAST')
            ->orderBy('name')
            ->paginate(25);

        $open = Assessment::query()->whereIn('process_id', $processes->getCollection()->modelKeys())
            ->whereIn('status', ['draft', 'submitted', 'returned'])->get()->keyBy('process_id');

        $exports = Export::query()->where('user_id', auth()->id())->where('type', BiaExport::TYPE)->latest()->limit(3)->get();

        return view('bia::livewire.processes.index', [
            'processes' => $processes,
            'figures' => $summary->figures(CarbonImmutable::today()),
            'exports' => $exports,
            'preparing' => $exports->contains(fn (Export $export): bool => $export->status === ExportStatus::Pending),
            'timezone' => config()->string('app.display_timezone'),
            'open' => $open,
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'tiers' => Tier::cases(),
        ])->title(__('bia::processes.title'));
    }
}
