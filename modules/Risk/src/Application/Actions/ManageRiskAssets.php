<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Models\User;
use Modules\Risk\Application\RiskAssets;
use Modules\Risk\Domain\Enums\AssetType;
use Modules\Risk\Domain\Models\Risk;

/**
 * Links a risk to what it concerns, checked through the module that owns it
 * (so only things of the organization), and unlinks it. Audited on the risk.
 */
final readonly class ManageRiskAssets
{
    public function __construct(
        private ConnectionInterface $db,
        private RiskAssets $assets,
        private AuditTrail $trail,
    ) {}

    /**
     * @throws InvalidArgumentException for something unknown in the organization
     */
    public function add(Risk $risk, AssetType $type, string $id, User $actor): void
    {
        $asset = $this->assets->find($type, [$id])[0] ?? throw new InvalidArgumentException('Unknown asset.');

        $this->db->table('risk_risk_assets')->insertOrIgnore(['risk_id' => $risk->id, 'organization_id' => $risk->organization_id, 'asset_type' => $type->value, 'asset_id' => $asset->id]);
        $this->trail->record(AuditEvent::Updated, $risk, null, ['asset_added' => $type->value.': '.$asset->name], $actor->id);
    }

    public function remove(Risk $risk, AssetType $type, string $id, User $actor): void
    {
        $name = $this->assets->find($type, [$id])[0]->name ?? $id;
        $this->db->table('risk_risk_assets')->where('risk_id', $risk->id)->where('asset_type', $type->value)->where('asset_id', $id)->delete();
        $this->trail->record(AuditEvent::Updated, $risk, ['asset_removed' => $type->value.': '.$name], null, $actor->id);
    }
}
