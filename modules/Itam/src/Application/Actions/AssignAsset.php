<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Modules\Core\Domain\Models\Location;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Itam\Domain\Enums\AssetStatus;
use Modules\Itam\Domain\Exceptions\AssetTransitionNotAllowed;
use Modules\Itam\Domain\Models\Asset;
use Modules\Itam\Domain\Models\AssetAssignment;

/**
 * Hands an asset to a person. An asset already with someone else moves to the
 * new person, and the previous assignment ends at the same moment.
 */
final readonly class AssignAsset
{
    public function __construct(
        private ConnectionInterface $db,
        private OrganizationContext $context,
    ) {}

    /**
     * @throws InvalidArgumentException for a person or location outside the organization
     * @throws AssetTransitionNotAllowed when the asset cannot be handed out now
     */
    public function handle(Asset $asset, User $holder, User $actor, ?string $locationId = null, ?string $notes = null): Asset
    {
        $organizationId = $this->context->requireId();

        if (! $holder->is_active || ! $holder->organizations()->whereKey($organizationId)->exists()) {
            throw new InvalidArgumentException('The person must be an active member of the organization.');
        }

        if ($locationId !== null && ! Location::query()->whereKey($locationId)->exists()) {
            throw new InvalidArgumentException('The location must belong to the organization.');
        }

        return $this->db->transaction(function () use ($asset, $holder, $actor, $locationId, $notes): Asset {
            // Locked: two people handing out the same laptop at once must not both succeed.
            $asset = Asset::query()->lockForUpdate()->findOrFail($asset->id);

            if ($asset->status === AssetStatus::Deployed && $asset->assigned_to_id === $holder->id) {
                throw AssetTransitionNotAllowed::because('The asset is already with this person.');
            }

            if ($asset->status !== AssetStatus::Deployed && ! $asset->status->canBecome(AssetStatus::Deployed)) {
                throw AssetTransitionNotAllowed::between($asset->status, AssetStatus::Deployed);
            }

            // From repair, an asset goes back to the person who had it; to someone else it is returned first.
            if ($asset->status === AssetStatus::InRepair && $asset->assigned_to_id !== null) {
                throw AssetTransitionNotAllowed::because('Return the asset from repair first.');
            }

            $now = now();
            AssetAssignment::query()->where('asset_id', $asset->id)->whereNull('returned_at')->update([
                'returned_at' => $now,
                'returned_by' => $actor->id,
            ]);

            $assignment = new AssetAssignment;
            $assignment->forceFill([
                'organization_id' => $asset->organization_id,
                'asset_id' => $asset->id,
                'user_id' => $holder->id,
                'assigned_at' => $now,
                'assigned_by' => $actor->id,
                'notes' => self::blankToNull($notes),
            ])->save();

            $asset->status = AssetStatus::Deployed;
            $asset->assigned_to_id = $holder->id;

            if ($locationId !== null) {
                $asset->location_id = $locationId;
            }

            $asset->save();

            return $asset;
        });
    }

    private static function blankToNull(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value === null || $value === '' ? null : mb_substr($value, 0, 1000);
    }
}
