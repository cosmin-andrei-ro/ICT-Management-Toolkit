<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Http\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Applications\Application\Exports\PortfolioExport;
use Modules\Applications\Application\Options\OptionCatalog;
use Modules\Applications\Domain\Models\Application;
use Modules\Applications\Domain\Models\Sheet;
use Modules\Core\Application\Exports\RequestExport;
use Modules\Core\Domain\Exports\ExportFormat;
use Modules\Core\Domain\Exports\ExportStatus;
use Modules\Core\Domain\Models\Export;
use Modules\Core\Domain\Models\User;

#[Layout('core::components.layouts.app')]
final class ApplicationIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $criticality = '';

    #[Url]
    public bool $due = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Application::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'criticality', 'due'], true)) {
            $this->resetPage();
        }
    }

    public function export(string $format, RequestExport $request): void
    {
        $this->authorize('viewAny', Application::class);
        $user = auth()->user();
        $format = ExportFormat::tryFrom($format);

        if (! $user instanceof User || $format === null) {
            return;
        }

        // RequestExport checks the permission and records the export in the audit trail.
        $request->handle($user, PortfolioExport::TYPE, $format, []);
    }

    public function render(OptionCatalog $options): View
    {
        $this->authorize('viewAny', Application::class);
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], trim(mb_substr($this->search, 0, 200))).'%';

        $applications = Application::query()
            ->with(['department', 'businessOwner', 'technicalOwner'])
            ->when(trim($this->search) !== '', fn ($query) => $query->where(fn ($inner) => $inner->whereLike('name', $like)->orWhereLike('code', $like)))
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->criticality !== '', fn ($query) => $query->where('criticality', $this->criticality))
            ->when($this->due, fn ($query) => $query->where(fn ($inner) => $inner->whereNull('approved_at')->orWhere('next_review_on', '<', today()->toDateString())))
            ->orderBy('name')
            ->paginate(25);

        return view('applications::livewire.applications.index', [
            'applications' => $applications,
            'open' => Sheet::query()->whereIn('application_id', $applications->getCollection()->modelKeys())->whereIn('status', ['draft', 'submitted', 'returned'])->get()->keyBy('application_id'),
            'statuses' => $options->choices('application_status'),
            'criticalities' => $options->choices('business_criticality'),
            'options' => $options,
            'exports' => $exports = Export::query()->where('user_id', auth()->id())->where('type', PortfolioExport::TYPE)->latest()->limit(3)->get(),
            'preparing' => $exports->contains(fn (Export $export): bool => $export->status === ExportStatus::Pending),
            'timezone' => config()->string('app.display_timezone'),
        ])->title(__('applications::applications.title'));
    }
}
