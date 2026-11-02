<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Links;

/**
 * An asset of the asset register as other modules see it (ADR 0047): what
 * identifies it on a rack drawing, and where it is.
 */
final readonly class PhysicalAsset
{
    public function __construct(
        public string $id,
        public string $tag,
        public string $name,
        public ?string $manufacturer,
        public ?string $model,
        public ?string $serialNumber,
        public string $status,
        public ?string $locationId,
        public string $url,
    ) {}

    public function label(): string
    {
        return $this->tag.' '.$this->name;
    }
}
