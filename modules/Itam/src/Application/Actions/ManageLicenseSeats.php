<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Application\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Itam\Domain\Enums\AssetStatus;
use Modules\Itam\Domain\Models\Asset;
use Modules\Itam\Domain\Models\License;
use Modules\Itam\Domain\Models\LicenseAssignment;

/**
 * Gives seats of a licence to people or devices, and takes them back. Using more
 * seats than were bought is recorded, not refused: the register must show what
 * is really installed, and the compliance view flags the excess. Changes are
 * audited on the licence.
 */
final readonly class ManageLicenseSeats
{
    public function __construct(
        private OrganizationContext $context,
        private AuditTrail $trail,
    ) {}

    /**
     * @throws InvalidArgumentException for a device licence, or someone outside the organization
     */
    public function giveToPerson(License $license, User $person, User $actor): void
    {
        if ($license->model->forDevices()) {
            throw new InvalidArgumentException('This licence is counted per device.');
        }

        if (! $person->is_active || ! $person->organizations()->whereKey($this->context->requireId())->exists()) {
            throw new InvalidArgumentException('The person must be an active member of the organization.');
        }

        $this->give($license, ['user_id' => $person->id], $person->name, $actor);
    }

    /**
     * @throws InvalidArgumentException for a person licence, or a disposed device
     */
    public function giveToDevice(License $license, Asset $asset, User $actor): void
    {
        if (! $license->model->forDevices()) {
            throw new InvalidArgumentException('This licence is counted per person.');
        }

        if ($asset->organization_id !== $license->organization_id || $asset->status === AssetStatus::Disposed) {
            throw new InvalidArgumentException('The device must be an asset of the organization still in the register.');
        }

        $this->give($license, ['asset_id' => $asset->id], $asset->asset_tag, $actor);
    }

    public function release(LicenseAssignment $seat, ?User $actor): void
    {
        $seat->loadMissing(['license', 'user', 'asset']);
        $holder = $seat->user->name ?? $seat->asset->asset_tag ?? $seat->id;
        $seat->delete();
        $this->trail->record(AuditEvent::Updated, $seat->license, ['seat' => $holder], null, $actor?->id);
    }

    /**
     * @param  array{user_id?: string, asset_id?: string}  $holder
     */
    private function give(License $license, array $holder, string $label, User $actor): void
    {
        $exists = LicenseAssignment::query()->where('license_id', $license->id)->where($holder)->exists();

        if ($exists) {
            return;
        }

        $seat = new LicenseAssignment;
        $seat->forceFill([
            'organization_id' => $license->organization_id,
            'license_id' => $license->id,
            ...$holder,
            'assigned_at' => now(),
            'assigned_by' => $actor->id,
        ])->save();

        $this->trail->record(AuditEvent::Updated, $license, null, ['seat' => $label], $actor->id);
    }
}
