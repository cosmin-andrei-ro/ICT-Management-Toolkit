<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Metrics;

use InvalidArgumentException;
use Modules\Core\Domain\Models\User;

/**
 * The indicators of every module (ADR 0044), for dashboards and reports that
 * must not read other modules' tables. Values are computed for the current
 * organization, only for sources with an indicator the viewer may see.
 */
final class Metrics
{
    /** @var array<string, MetricSource> */
    private array $sources = [];

    public function register(MetricSource $source): void
    {
        foreach ($source->metrics as $metric) {
            if (! str_starts_with($metric->key, $source->key.'.')) {
                throw new InvalidArgumentException("Metric [{$metric->key}] must start with [{$source->key}.].");
            }
        }

        $this->sources[$source->key] = $source;
    }

    /**
     * @return array<string, MetricSource>
     */
    public function sources(): array
    {
        return $this->sources;
    }

    public function metric(string $key): ?Metric
    {
        foreach ($this->sources as $source) {
            foreach ($source->metrics as $metric) {
                if ($metric->key === $key) {
                    return $metric;
                }
            }
        }

        return null;
    }

    /**
     * The current value of one indicator, computing only its source.
     */
    public function current(string $key): int|float|null
    {
        foreach ($this->sources as $source) {
            foreach ($source->metrics as $metric) {
                if ($metric->key === $key) {
                    return ($source->values)()[$key] ?? null;
                }
            }
        }

        return null;
    }

    /**
     * The groups and values the person may see, in registration order.
     *
     * @return list<array{source: MetricSource, metrics: list<array{metric: Metric, value: int|float|null, status: MetricStatus}>}>
     */
    public function visibleTo(User $viewer): array
    {
        $groups = [];

        foreach ($this->sources as $source) {
            $visible = array_values(array_filter($source->metrics, fn (Metric $metric): bool => $viewer->can($metric->permission)));

            if ($visible === []) {
                continue;
            }

            $values = ($source->values)();
            $groups[] = [
                'source' => $source,
                'metrics' => array_map(fn (Metric $metric): array => [
                    'metric' => $metric,
                    'value' => $values[$metric->key] ?? null,
                    'status' => $metric->status($values[$metric->key] ?? null),
                ], $visible),
            ];
        }

        return $groups;
    }

    /**
     * Every value of the current organization, for snapshots.
     *
     * @return array<string, int|float|null>
     */
    public function all(): array
    {
        $values = [];

        foreach ($this->sources as $source) {
            $computed = ($source->values)();

            foreach ($source->metrics as $metric) {
                $values[$metric->key] = $computed[$metric->key] ?? null;
            }
        }

        return $values;
    }
}
