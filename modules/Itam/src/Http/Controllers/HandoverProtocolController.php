<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Http\Controllers;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Core\Domain\Models\Location;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Itam\Domain\Models\AssetAssignment;

/**
 * The handover (and, once returned, return) protocol of one assignment, as a page
 * to print or save as PDF. For the holder and for IT staff who see assets.
 */
final readonly class HandoverProtocolController
{
    public function __construct(
        private Gate $gate,
        private OrganizationContext $context,
    ) {}

    public function __invoke(Request $request, string $assignment): View
    {
        // Scoped: an assignment of another organization is not found.
        $record = AssetAssignment::query()->with(['asset.category', 'user', 'assigner', 'receiver'])->findOrFail($assignment);
        $user = $request->user();

        abort_unless($user !== null && ($record->user_id === $user->getAuthIdentifier() || $this->gate->allows('view', $record->asset)), 403);

        $location = $record->asset->location_id !== null ? (Location::paths(false)[$record->asset->location_id] ?? null) : null;

        return view('itam::handover', [
            'assignment' => $record,
            'asset' => $record->asset,
            'organization' => Organization::query()->find($this->context->requireId()),
            'location' => $location,
            'fields' => $record->asset->category->schema()->fields,
            'timezone' => config()->string('app.display_timezone'),
        ]);
    }
}
