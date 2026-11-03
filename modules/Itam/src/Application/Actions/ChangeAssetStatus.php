<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Modules\Core\Domain\Models\Location;
use Modules\Core\Domain\Models\User;
use Modules\Itam\Domain\Enums\AssetStatus;
use Modules\Itam\Domain\Exceptions\AssetTransitionNotAllowed;
use Modules\Itam\Domain\Models\Asset;
use Modules\Itam\Domain\Models\AssetAssignment;

/**
 * Moves an asset along its life cycle: received, put in service at a location
 * (infrastructure, with no holder), returned to stock, sent for repair and
 * back, retired, lost or found. Handing out is AssignAsset and
 * disposal is DisposeAsset. An asset away for repair stays with its holder;
 * any other move ends the current assignment.
 */
final readonly class ChangeAssetStatus
{
    public function __construct(private ConnectionInterface $db) {}

    /**
     * @throws AssetTransitionNotAllowed
     * @throws InvalidArgumentException for a location outside the organization
     */
    public function handle(Asset $asset, AssetStatus $target, User $actor, ?string $locationId = null, ?string $notes = null): Asset
    {
        if ($target === AssetStatus::Disposed || $target === AssetStatus::Ordered) {
            throw AssetTransitionNotAllowed::because("Use the dedicated action to make an asset [{$target->value}].");
        }

        if ($locationId !== null && ! Location::query()->whereKey($locationId)->exists()) {
            throw new InvalidArgumentException('The location must belong to the organization.');
        }

        return $this->db->transaction(function () use ($asset, $target, $actor, $locationId, $notes): Asset {
            $asset = Asset::query()->lockForUpdate()->findOrFail($asset->id);

            if (! $asset->status->canBecome($target)) {
                throw AssetTransitionNotAllowed::between($asset->status, $target);
            }

            // Without a holder, "in use" means put in service at a location (a server in its
            // rack, a switch in the comms room); for a person, it is handed out with AssignAsset.
            if ($target === AssetStatus::Deployed && $asset->assigned_to_id === null && ($locationId ?? $asset->location_id) === null) {
                throw AssetTransitionNotAllowed::because('Choose who receives the asset, or the location where it is put in service.');
            }

            if (! in_array($target, [AssetStatus::InRepair, AssetStatus::Deployed], true) && $asset->assigned_to_id !== null) {
                $note = $notes !== null && trim($notes) !== '' ? mb_substr(trim($notes), 0, 1000) : null;
                AssetAssignment::query()->where('asset_id', $asset->id)->whereNull('returned_at')->update([
                    'returned_at' => now(),
                    'returned_by' => $actor->id,
                    'return_notes' => $note ?? $target->label(),
                ]);
                $asset->assigned_to_id = null;
            }

            $asset->status = $target;

            if ($locationId !== null) {
                $asset->location_id = $locationId;
            }

            $asset->save();

            return $asset;
        });
    }
}
