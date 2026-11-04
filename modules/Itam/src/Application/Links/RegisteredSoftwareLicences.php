<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Application\Links;

use Illuminate\Support\Facades\Gate;
use Modules\Core\Domain\Links\SoftwareLicences;
use Modules\Core\Domain\Models\User;
use Modules\Itam\Domain\Models\License;
use Modules\Itam\Domain\Models\SoftwareProduct;

/**
 * The asset module's side of Core's SoftwareLicences contract.
 */
final class RegisteredSoftwareLicences implements SoftwareLicences
{
    public function isAvailable(): bool
    {
        return true;
    }

    public function isRecorded(string $productName): bool
    {
        return SoftwareProduct::query()->whereRaw('lower(name) = ?', [mb_strtolower(trim($productName))])->exists();
    }

    public function newLicenceUrl(string $productName, User $viewer): ?string
    {
        return Gate::forUser($viewer)->allows('create', License::class)
            ? route('itam.licenses.create', ['new_product' => mb_substr($productName, 0, 255)])
            : null;
    }
}
