<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Modules\Core\Domain\Models\User;
use Modules\Itam\Domain\Enums\AssetStatus;
use Modules\Itam\Domain\Enums\DisposalMethod;
use Modules\Itam\Domain\Exceptions\AssetTransitionNotAllowed;
use Modules\Itam\Domain\Models\Asset;
use Modules\Itam\Domain\Models\LicenseAssignment;

/**
 * The end of an asset's life: sold, donated, recycled, destroyed or returned to
 * the lessor, with whether its data was wiped. Final. Proof (a destruction
 * certificate) goes in the asset's documents. The device's licence seats are
 * released, so they can go to another device.
 */
final readonly class DisposeAsset
{
    public function __construct(
        private ConnectionInterface $db,
        private ManageLicenseSeats $seats,
    ) {}

    /**
     * @param  ?bool  $dataWiped  Null for an asset that stores no data.
     * @param  ?User  $actor  Who disposed of it, for the licence audit entries.
     *
     * @throws AssetTransitionNotAllowed unless the asset is retired or lost
     */
    public function handle(Asset $asset, CarbonImmutable $on, DisposalMethod $method, ?bool $dataWiped, ?string $notes = null, ?User $actor = null): Asset
    {
        return $this->db->transaction(function () use ($asset, $on, $method, $dataWiped, $notes, $actor): Asset {
            $asset = Asset::query()->lockForUpdate()->findOrFail($asset->id);

            if (! $asset->status->canBecome(AssetStatus::Disposed)) {
                throw AssetTransitionNotAllowed::between($asset->status, AssetStatus::Disposed);
            }

            $asset->forceFill([
                'status' => AssetStatus::Disposed,
                'disposed_on' => $on->toDateString(),
                'disposal_method' => $method,
                'data_wiped' => $dataWiped,
                'disposal_notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
            ])->save();

            foreach (LicenseAssignment::query()->where('asset_id', $asset->id)->get() as $seat) {
                $this->seats->release($seat, $actor);
            }

            return $asset;
        });
    }
}
