<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Http\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Core\Application\Exports\RequestExport;
use Modules\Core\Domain\Exports\ExportFormat;
use Modules\Core\Domain\Exports\ExportStatus;
use Modules\Core\Domain\Models\Export;
use Modules\Core\Domain\Models\User;
use Modules\Itam\Application\Exports\LicenseExport;
use Modules\Itam\Application\Licenses\LicenseCompliance;
use Modules\Itam\Application\Licenses\ProductCompliance;
use Modules\Itam\Domain\Models\License;

/**
 * Licences grouped by product, with seats bought against seats in use.
 */
#[Layout('core::components.layouts.app')]
final class LicenseIndex extends Component
{
    #[Url]
    public string $search = '';

    #[Url]
    public bool $expired = false;

    /** Only products using more seats than they have. */
    #[Url]
    public bool $over = false;

    public function mount(): void
    {
        $this->authorize('viewAny', License::class);
    }

    public function export(string $format, RequestExport $request): void
    {
        $user = auth()->user();
        $format = ExportFormat::tryFrom($format);

        if (! $user instanceof User || $format === null) {
            return;
        }

        $request->handle($user, LicenseExport::TYPE, $format, []);
    }

    public function render(LicenseCompliance $compliance): View
    {
        $this->authorize('viewAny', License::class);
        $today = CarbonImmutable::today();
        $products = $compliance->products($today, mb_substr($this->search, 0, 200), $this->expired)
            ->when($this->over, fn ($products) => $products->filter(fn (ProductCompliance $product): bool => $product->isOverAllocated())->values());
        $exports = Export::query()->where('user_id', auth()->id())->where('type', LicenseExport::TYPE)->latest()->limit(3)->get();

        return view('itam::livewire.licenses.index', [
            'products' => $products,
            'today' => $today,
            'exports' => $exports,
            'preparing' => $exports->contains(fn (Export $export): bool => $export->status === ExportStatus::Pending),
            'timezone' => config()->string('app.display_timezone'),
        ])->title(__('itam::licenses.title'));
    }
}
