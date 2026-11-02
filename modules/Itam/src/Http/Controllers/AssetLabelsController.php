<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Http\Controllers;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Itam\Domain\Enums\AssetStatus;
use Modules\Itam\Domain\Models\Asset;
use Modules\Itam\Infrastructure\QrCodes;

/**
 * A sheet of labels to print, one per asset, for the assets the list shows (same
 * filters). The QR code holds only the address of the asset page, which asks
 * for sign-in and permissions: a label reveals nothing by itself.
 */
final readonly class AssetLabelsController
{
    /** Labels per sheet run; more would make an unwieldy print job. */
    public const int MAX = 240;

    public function __construct(
        private Gate $gate,
        private QrCodes $qrCodes,
        private OrganizationContext $context,
    ) {}

    public function __invoke(Request $request): View
    {
        $this->gate->authorize('create', Asset::class);

        $search = mb_substr((string) $request->query('search', ''), 0, 200);
        $category = (string) $request->query('category', '');
        $status = (string) $request->query('status', 'active');
        $location = (string) $request->query('location', '');

        $assets = Asset::query()
            ->with('category')
            ->search($search)
            ->when(Str::isUuid($category), fn ($query) => $query->where('category_id', $category))
            ->when(AssetStatus::tryFrom($status) !== null, fn ($query) => $query->where('status', $status))
            ->when(AssetStatus::tryFrom($status) === null, fn ($query) => $query->where('status', '!=', AssetStatus::Disposed->value))
            ->when(Str::isUuid($location), fn ($query) => $query->where('location_id', $location))
            ->orderBy('asset_tag')
            ->limit(self::MAX + 1)
            ->get();

        return view('itam::labels', [
            'labels' => $assets->take(self::MAX)->map(fn (Asset $asset): array => [
                'tag' => $asset->asset_tag,
                'name' => $asset->displayName(),
                'qr' => $this->qrCodes->svgDataUrl(route('itam.assets.show', $asset)),
            ]),
            'truncated' => $assets->count() > self::MAX,
            'organization' => Organization::query()->find($this->context->requireId())?->name,
        ]);
    }
}
