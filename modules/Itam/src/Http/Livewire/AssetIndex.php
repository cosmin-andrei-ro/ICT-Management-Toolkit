<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Http\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Core\Application\Exports\RequestExport;
use Modules\Core\Domain\Exports\ExportFormat;
use Modules\Core\Domain\Exports\ExportStatus;
use Modules\Core\Domain\Models\Export;
use Modules\Core\Domain\Models\Location;
use Modules\Core\Domain\Models\User;
use Modules\Itam\Application\Actions\ProvisionDefaultCategories;
use Modules\Itam\Application\Exports\AssetExport;
use Modules\Itam\Domain\Enums\AssetStatus;
use Modules\Itam\Domain\Models\Asset;
use Modules\Itam\Domain\Models\Category;

#[Layout('core::components.layouts.app')]
final class AssetIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $category = '';

    /** A status, or "active" for everything not yet disposed. */
    #[Url]
    public string $status = 'active';

    #[Url]
    public string $location = '';

    /** Only warranties ending within the reminder window. */
    #[Url]
    public bool $warranty = false;

    public function mount(ProvisionDefaultCategories $provision): void
    {
        $this->authorize('viewAny', Asset::class);
        $provision->handle();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'category', 'status', 'location', 'warranty'], true)) {
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
        $request->handle($user, AssetExport::TYPE, $format, []);
    }

    public function render(Config $config): View
    {
        $this->authorize('viewAny', Asset::class);
        $status = AssetStatus::tryFrom($this->status);
        $today = CarbonImmutable::today();
        $days = $config->get('itam.assets.warranty_reminder_days');

        $assets = Asset::query()
            ->with(['category', 'holder', 'location'])
            ->search(mb_substr($this->search, 0, 200))
            ->when($this->category !== '' && Str::isUuid($this->category), fn ($query) => $query->where('category_id', $this->category))
            ->when($status !== null, fn ($query) => $query->where('status', $this->status))
            ->when($status === null && $this->status === 'active', fn ($query) => $query->where('status', '!=', AssetStatus::Disposed->value))
            ->when($this->location !== '' && Str::isUuid($this->location), fn ($query) => $query->where('location_id', $this->location))
            ->when($this->warranty, fn ($query) => $query->whereBetween('warranty_ends_on', [$today->toDateString(), $today->addDays(is_int($days) && $days > 0 ? $days : 30)->toDateString()]))
            ->orderBy('asset_tag')
            ->paginate(25);

        $exports = Export::query()->where('user_id', auth()->id())->where('type', AssetExport::TYPE)->latest()->limit(3)->get();

        return view('itam::livewire.index', [
            'assets' => $assets,
            'exports' => $exports,
            'preparing' => $exports->contains(fn (Export $export): bool => $export->status === ExportStatus::Pending),
            'timezone' => config()->string('app.display_timezone'),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => AssetStatus::cases(),
            'locations' => Location::paths(),
        ])->title(__('itam::assets.title'));
    }
}
