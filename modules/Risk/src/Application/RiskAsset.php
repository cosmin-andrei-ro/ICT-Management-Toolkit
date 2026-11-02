<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Application;

use Modules\Risk\Domain\Enums\AssetType;

/**
 * Something a risk concerns, as shown: its kind, name and page.
 */
final readonly class RiskAsset
{
    public function __construct(
        public AssetType $type,
        public string $id,
        public string $name,
        public ?string $detail,
        public ?string $url,
    ) {}
}
