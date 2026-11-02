<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Application\Risks;

use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Links\PhysicalAssets;
use Modules\Core\Domain\Risks\RiskSignal;
use Modules\Dcim\Application\PowerGrid;
use Modules\Dcim\Domain\Models\PowerSource;

/**
 * Power risks for the risk register (ADR 0049): equipment the CMDB relies on
 * with a single power supply, and supplies that would be overloaded if the
 * other side failed.
 */
final readonly class PowerRiskSignals
{
    public function __construct(private ConfigurationItems $items, private PhysicalAssets $assets) {}

    /**
     * @return list<RiskSignal>
     */
    public function __invoke(): array
    {
        $grid = PowerGrid::read();
        $signals = [];
        $source = (string) __('dcim::power.risk.source');

        $single = $grid->singleFed();
        $assetIds = array_values(array_unique(array_map(fn ($mount): string => $mount->asset_id, $single)));
        $linked = $this->items->linkedIds('itam.asset', $assetIds);
        $labels = $this->assets->find($assetIds);

        foreach ($single as $mount) {
            $itemIds = $linked[$mount->asset_id] ?? [];

            // Equipment nobody relies on in the CMDB is not worth a risk.
            if ($itemIds === []) {
                continue;
            }

            $replace = ['equipment' => ($labels[$mount->asset_id] ?? null)?->label() ?? $mount->asset_id];
            $signals[] = new RiskSignal(
                'dcim.single_fed:'.$mount->id, $source,
                (string) __('dcim::power.risk.single_fed.title', $replace),
                (string) __('dcim::power.risk.single_fed.description', $replace),
                'availability', array_map(fn (string $id): array => ['type' => 'configuration_item', 'id' => $id], array_values(array_unique($itemIds))),
                ['Power failure'], ['Single point of failure'],
                2, 4, route('dcim.equipment.show', $mount),
            );
        }

        foreach (PowerSource::query()->whereNotNull('capacity_watts')->get() as $power) {
            $load = $grid->loadOf($power);

            if ($power->capacity_watts === null || $load['worst'] <= $power->capacity_watts) {
                continue;
            }

            $replace = ['source' => $power->name, 'load' => (int) round($load['worst']), 'capacity' => $power->capacity_watts];
            $signals[] = new RiskSignal(
                'dcim.overload:'.$power->id, $source,
                (string) __('dcim::power.risk.overload.title', $replace),
                (string) __('dcim::power.risk.overload.description', $replace),
                'availability', [],
                ['Power failure'], ['Single point of failure'],
                2, 4, route('dcim.power.show', $power),
            );
        }

        return $signals;
    }
}
