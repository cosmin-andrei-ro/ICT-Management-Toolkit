<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Application\Risks;

use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Risks\RiskSignal;
use Modules\Itam\Domain\Enums\AssetStatus;
use Modules\Itam\Domain\Models\Asset;
use Modules\Itam\Domain\Models\License;

/**
 * Asset risks: software licences expiring or expired (use without a licence),
 * and deployed equipment out of warranty that runs a CMDB item.
 */
final readonly class AssetRiskSignals
{
    public const int LICENCE_DAYS = 30;

    public function __construct(private ConfigurationItems $items) {}

    /**
     * @return list<RiskSignal>
     */
    public function __invoke(): array
    {
        $signals = [];
        $source = (string) __('itam::risk_signals.source');

        foreach (License::query()->with('product')->whereNotNull('expires_on')->where('expires_on', '<=', today()->addDays(self::LICENCE_DAYS)->toDateString())->limit(500)->get() as $licence) {
            $replace = ['licence' => $licence->name, 'product' => $licence->product->name, 'date' => $licence->expires_on?->toDateString()];
            $signals[] = new RiskSignal(
                'itam.licence:'.$licence->id, $source,
                (string) __('itam::risk_signals.licence.title', $replace),
                (string) __('itam::risk_signals.licence.description', $replace),
                'compliance', $licence->supplier_id !== null ? [['type' => 'supplier', 'id' => $licence->supplier_id]] : [],
                ['Breach of law or contract'], [],
                4, 3, route('itam.licenses.show', $licence),
            );
        }

        $expired = Asset::query()->where('status', AssetStatus::Deployed->value)->whereNotNull('warranty_ends_on')->where('warranty_ends_on', '<', today()->toDateString())->limit(1000)->get();
        $linked = $this->items->linkedIds((new Asset)->getMorphClass(), array_values($expired->modelKeys()));

        foreach ($expired as $asset) {
            $itemIds = $linked[$asset->id] ?? [];

            // Equipment nobody relies on in the CMDB is not worth a risk.
            if ($itemIds === []) {
                continue;
            }

            $replace = ['asset' => $asset->name ?? $asset->asset_tag, 'date' => $asset->warranty_ends_on?->toDateString()];
            $signals[] = new RiskSignal(
                'itam.warranty:'.$asset->id, $source,
                (string) __('itam::risk_signals.warranty.title', $replace),
                (string) __('itam::risk_signals.warranty.description', $replace),
                'availability', array_map(fn (string $id): array => ['type' => 'configuration_item', 'id' => $id], array_values(array_unique($itemIds))),
                ['Hardware failure'], ['Single point of failure'],
                3, null, route('itam.assets.show', $asset),
            );
        }

        return $signals;
    }
}
