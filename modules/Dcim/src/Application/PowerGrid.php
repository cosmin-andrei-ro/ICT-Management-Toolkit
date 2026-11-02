<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Application;

use Modules\Dcim\Domain\Models\Mount;
use Modules\Dcim\Domain\Models\PowerConnection;
use Modules\Dcim\Domain\Models\PowerSource;

/**
 * The power chain of the organization, read once (ADR 0049): what each
 * source carries, which equipment has two independent supplies, and what a
 * failure takes down.
 *
 * Equipment plugged into n supplies draws its power evenly from them in
 * normal operation; when the others fail, one carries all of it ("worst").
 */
final class PowerGrid
{
    /** @var array<string, list<string>> Source id => itself and every source above it. */
    private array $chains = [];

    /**
     * @param  array<string, PowerSource>  $sources
     * @param  array<string, non-empty-list<PowerConnection>>  $byMount
     */
    private function __construct(
        private readonly array $sources,
        private readonly array $byMount,
    ) {}

    public static function read(): self
    {
        $sources = [];
        $byMount = [];

        foreach (PowerSource::query()->get() as $source) {
            $sources[$source->id] = $source;
        }

        foreach (PowerConnection::query()->with('mount')->get() as $connection) {
            $byMount[$connection->mount_id][] = $connection;
        }

        return new self($sources, $byMount);
    }

    /**
     * How many of these connections run through the source.
     *
     * @param  list<PowerConnection>  $connections
     */
    private function under(array $connections, string $sourceId): int
    {
        return count(array_filter($connections, fn (PowerConnection $connection): bool => in_array($sourceId, $this->chain($connection->source_id), true)));
    }

    /**
     * @return list<string>
     */
    public function chain(string $sourceId): array
    {
        if (isset($this->chains[$sourceId])) {
            return $this->chains[$sourceId];
        }

        $chain = [];

        for ($id = $sourceId; $id !== null && isset($this->sources[$id]) && ! in_array($id, $chain, true); $id = $this->sources[$id]->upstream_id) {
            $chain[] = $id;
        }

        return $this->chains[$sourceId] = $chain;
    }

    /**
     * @return array{normal: float, worst: float, devices: int, unknown: int}
     */
    public function loadOf(PowerSource $source): array
    {
        $normal = 0.0;
        $worst = 0.0;
        $devices = 0;
        $unknown = 0;

        foreach ($this->byMount as $connections) {
            $under = $this->under($connections, $source->id);

            if ($under === 0) {
                continue;
            }

            $devices++;
            $watts = $connections[0]->mount->power_watts;

            if ($watts === null) {
                $unknown++;

                continue;
            }

            $normal += $watts * $under / count($connections);
            $worst += $watts;
        }

        return ['normal' => $normal, 'worst' => $worst, 'devices' => $devices, 'unknown' => $unknown];
    }

    /**
     * Two supplies that share no source above them: a single failure leaves it running.
     */
    public function isRedundant(string $mountId): bool
    {
        $chains = array_map(fn (PowerConnection $connection): array => $this->chain($connection->source_id), $this->byMount[$mountId] ?? []);

        foreach ($chains as $i => $first) {
            foreach (array_slice($chains, $i + 1) as $second) {
                if (array_intersect($first, $second) === []) {
                    return true;
                }
            }
        }

        return false;
    }

    public function isPowered(string $mountId): bool
    {
        return isset($this->byMount[$mountId]);
    }

    /**
     * Mounted equipment with power but no second independent supply.
     *
     * @return list<Mount>
     */
    public function singleFed(): array
    {
        $single = [];

        foreach ($this->byMount as $mountId => $connections) {
            if (! $this->isRedundant((string) $mountId)) {
                $single[] = $connections[0]->mount;
            }
        }

        return $single;
    }

    /**
     * @return array{lost: list<Mount>, degraded: list<Mount>} Equipment left without power, and equipment left on one supply.
     */
    public function failureOf(PowerSource $source): array
    {
        $lost = [];
        $degraded = [];

        foreach ($this->byMount as $connections) {
            $hit = $this->under($connections, $source->id);

            if ($hit === 0) {
                continue;
            }

            $mount = $connections[0]->mount;

            if ($hit === count($connections)) {
                $lost[] = $mount;
            } else {
                $degraded[] = $mount;
            }
        }

        return ['lost' => $lost, 'degraded' => $degraded];
    }

    /**
     * Load as a share of capacity, null when the capacity is unknown.
     */
    public static function percent(float $watts, ?int $capacity): ?int
    {
        return $capacity !== null && $capacity > 0 ? (int) round($watts / $capacity * 100) : null;
    }
}
