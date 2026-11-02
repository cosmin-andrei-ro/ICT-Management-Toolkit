<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Metrics;

use Closure;

/**
 * A module's indicators, computed together (one pass over its data) for the
 * current organization.
 */
final readonly class MetricSource
{
    /**
     * @param  string  $key  The module, e.g. "ticketing".
     * @param  string  $label  Translation key of the group heading.
     * @param  list<Metric>  $metrics
     * @param  Closure(): array<string, int|float|null>  $values  By metric key.
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $metrics,
        public Closure $values,
    ) {}
}
