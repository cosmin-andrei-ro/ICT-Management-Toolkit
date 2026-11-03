<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Cmdb\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Cmdb\Application\Actions\ProvisionDefaultTypes;
use Modules\Cmdb\Domain\Enums\CiStatus;
use Modules\Cmdb\Domain\Enums\Criticality;
use Modules\Cmdb\Domain\Enums\Environment;
use Modules\Cmdb\Domain\Models\CiType;
use Modules\Cmdb\Domain\Models\ConfigurationItem;

#[Layout('core::components.layouts.app')]
final class ItemIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $type = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $environment = '';

    #[Url]
    public string $criticality = '';

    public function mount(ProvisionDefaultTypes $provision): void
    {
        $this->authorize('viewAny', ConfigurationItem::class);
        $provision->handle();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'type', 'status', 'environment', 'criticality'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $this->authorize('viewAny', ConfigurationItem::class);

        $items = ConfigurationItem::query()
            ->with(['type', 'owner', 'team'])
            ->search(mb_substr($this->search, 0, 200))
            ->when(Str::isUuid($this->type), fn ($query) => $query->where('type_id', $this->type))
            ->when(CiStatus::tryFrom($this->status) !== null, fn ($query) => $query->where('status', $this->status))
            ->when(Environment::tryFrom($this->environment) !== null, fn ($query) => $query->where('environment', $this->environment))
            ->when(Criticality::tryFrom($this->criticality) !== null, fn ($query) => $query->where('criticality', $this->criticality))
            ->orderBy('name')
            ->paginate(25);

        return view('cmdb::livewire.index', [
            'items' => $items,
            'types' => CiType::query()->orderBy('name')->get(),
            'statuses' => CiStatus::cases(),
            'environments' => Environment::cases(),
            'criticalities' => Criticality::cases(),
        ])->title(__('cmdb::items.title'));
    }
}
