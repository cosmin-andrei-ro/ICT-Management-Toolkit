<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Monitoring;

use Modules\Ticketing\Domain\Enums\Impact;
use Modules\Ticketing\Domain\Enums\Urgency;

/**
 * One alert as a monitoring tool reports it, whatever its format.
 */
final readonly class AlertSignal
{
    public const array SEVERITIES = ['critical', 'major', 'minor', 'warning', 'info'];

    public function __construct(
        public string $key,
        public bool $firing,
        public string $title,
        public ?string $description = null,
        public string $severity = 'major',
        /** A host or check name, matched to a CMDB item by identifier, then by name. */
        public ?string $item = null,
        public ?string $itemId = null,
        public ?string $source = null,
    ) {}

    /**
     * @return array{Impact, Urgency}
     */
    public function priority(): array
    {
        return match ($this->severity) {
            'critical' => [Impact::High, Urgency::High],
            'major' => [Impact::Medium, Urgency::High],
            'minor', 'warning' => [Impact::Medium, Urgency::Medium],
            default => [Impact::Low, Urgency::Low],
        };
    }
}
