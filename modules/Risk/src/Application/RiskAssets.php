<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Application;

use Modules\Core\Domain\Links\Applications;
use Modules\Core\Domain\Links\BusinessProcesses;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Models\Supplier;
use Modules\Risk\Domain\Enums\AssetType;

/**
 * What risks can concern, found and named through the modules that own them:
 * applications, CMDB items and BIA processes through Core contracts,
 * suppliers from Core. Kinds whose module is disabled offer nothing.
 */
final readonly class RiskAssets
{
    public function __construct(
        private Applications $applications,
        private ConfigurationItems $items,
        private BusinessProcesses $processes,
    ) {}

    public function isAvailable(AssetType $type): bool
    {
        return match ($type) {
            AssetType::Application => $this->applications->isAvailable(),
            AssetType::ConfigurationItem => $this->items->isAvailable(),
            AssetType::Process => $this->processes->isAvailable(),
            AssetType::Supplier => true,
        };
    }

    /**
     * @return list<RiskAsset>
     */
    public function search(AssetType $type, string $terms): array
    {
        $terms = trim(mb_substr($terms, 0, 200));

        if (mb_strlen($terms) < 2) {
            return [];
        }

        return match ($type) {
            AssetType::Application => array_map(fn ($application): RiskAsset => new RiskAsset($type, $application->id, $application->name, $application->code, $application->url), $this->applications->search($terms)),
            AssetType::ConfigurationItem => array_map(fn ($item): RiskAsset => new RiskAsset($type, $item->id, $item->name, $item->typeName, $item->url), $this->items->search($terms)),
            AssetType::Process => array_map(fn ($process): RiskAsset => new RiskAsset($type, $process->id, $process->name, null, $process->url), $this->processes->search($terms)),
            AssetType::Supplier => array_values(Supplier::query()->whereLike('name', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $terms).'%')->orderBy('name')->limit(10)->get()
                ->map(fn (Supplier $supplier): RiskAsset => new RiskAsset($type, $supplier->id, $supplier->name, null, null))->all()),
        };
    }

    /**
     * @param  list<array{type: string, id: string}>  $references
     * @return list<RiskAsset> Known ones, in kind order; unknown ones are left out.
     */
    public function resolve(array $references): array
    {
        $byType = [];

        foreach ($references as $reference) {
            $type = AssetType::tryFrom($reference['type']);

            if ($type !== null) {
                $byType[$type->value][] = $reference['id'];
            }
        }

        $assets = [];

        foreach (AssetType::cases() as $type) {
            $ids = $byType[$type->value] ?? [];

            if ($ids === []) {
                continue;
            }

            foreach ($this->find($type, $ids) as $asset) {
                $assets[] = $asset;
            }
        }

        return $assets;
    }

    /**
     * @param  list<string>  $ids
     * @return list<RiskAsset>
     */
    public function find(AssetType $type, array $ids): array
    {
        return match ($type) {
            AssetType::Application => array_values(array_map(fn ($application): RiskAsset => new RiskAsset($type, $application->id, $application->name, $application->code, $application->url), $this->applications->find($ids))),
            AssetType::ConfigurationItem => array_values(array_map(fn ($item): RiskAsset => new RiskAsset($type, $item->id, $item->name, $item->typeName, $item->url), $this->items->recoveryProfiles($ids))),
            AssetType::Process => array_values(array_map(fn ($process): RiskAsset => new RiskAsset($type, $process->id, $process->name, null, $process->url), $this->processes->find($ids))),
            AssetType::Supplier => array_values(Supplier::query()->whereKey($ids)->orderBy('name')->get()
                ->map(fn (Supplier $supplier): RiskAsset => new RiskAsset($type, $supplier->id, $supplier->name, null, null))->all()),
        };
    }
}
