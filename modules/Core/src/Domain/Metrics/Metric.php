<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Metrics;

/**
 * A key performance indicator a module offers the dashboards (ADR 0044): what
 * it is, who may see it, and when it needs attention.
 */
final readonly class Metric
{
    /**
     * @param  string  $key  Unique, e.g. "ticketing.unassigned"; also the key of the source's values.
     * @param  string  $label  Translation key.
     * @param  string  $permission  Needed to see it (checked in the current organization).
     * @param  ?float  $warnAt  Value from which it needs attention (or below which, when higher is better).
     * @param  ?float  $alertAt  Value from which it is a problem.
     * @param  ?string  $route  Where the records behind it are listed.
     * @param  array<string, mixed>  $routeParameters
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $permission,
        public MetricUnit $unit = MetricUnit::Count,
        public bool $lowerIsBetter = true,
        public ?float $warnAt = null,
        public ?float $alertAt = null,
        public ?string $route = null,
        public array $routeParameters = [],
    ) {}

    public function status(int|float|null $value): MetricStatus
    {
        if ($value === null || ($this->warnAt === null && $this->alertAt === null)) {
            return MetricStatus::Neutral;
        }

        $reached = fn (?float $threshold): bool => $threshold !== null && ($this->lowerIsBetter ? $value >= $threshold : $value <= $threshold);

        return match (true) {
            $reached($this->alertAt) => MetricStatus::Alert,
            $reached($this->warnAt) => MetricStatus::Warning,
            default => MetricStatus::Good,
        };
    }

    public function url(): ?string
    {
        return $this->route !== null ? route($this->route, $this->routeParameters) : null;
    }
}
