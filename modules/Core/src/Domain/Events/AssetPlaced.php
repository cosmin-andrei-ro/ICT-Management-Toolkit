<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Events;

/**
 * An asset was mounted in a rack (ADR 0047): asset management records the
 * rack's room as the asset's location.
 */
final readonly class AssetPlaced
{
    public function __construct(
        public string $organizationId,
        public string $assetId,
        public string $locationId,
        public ?string $actorId,
    ) {}
}
