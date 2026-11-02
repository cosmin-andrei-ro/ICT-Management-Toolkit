<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Risk\Domain\Enums\RiskCategory;
use Modules\Risk\Domain\Enums\RiskStatus;
use Modules\Risk\Domain\Models\Risk;
use Modules\Risk\Domain\Models\RiskSettings;

/**
 * Registers a risk (numbered R-0001… per organization) or changes its
 * identification. Audited by the risk's own trail.
 */
final readonly class SaveRisk
{
    public function __construct(
        private ConnectionInterface $db,
        private OrganizationContext $context,
    ) {}

    /**
     * @param  array{title: string, description: ?string, category: RiskCategory, owner_id: ?string, status: RiskStatus}  $data  Validated.
     */
    public function handle(?Risk $risk, array $data): Risk
    {
        return $this->db->transaction(function () use ($risk, $data): Risk {
            if ($risk === null) {
                $organizationId = $this->context->requireId();
                // Serializes numbering within the organization.
                $this->db->table('core_organizations')->where('id', $organizationId)->lockForUpdate()->first();
                $max = Risk::query()->withTrashed()->max('reference');
                $risk = new Risk;
                $risk->forceFill([
                    'organization_id' => $organizationId,
                    'reference' => (is_numeric($max) ? (int) $max : 0) + 1,
                    'next_review_on' => now()->addMonthsNoOverflow(RiskSettings::current()->review_months)->toDateString(),
                ]);
            }

            $description = $data['description'] !== null ? trim($data['description']) : '';

            $risk->forceFill([
                'title' => trim($data['title']),
                'description' => $description !== '' ? $description : null,
                'category' => $data['category'],
                'owner_id' => $data['owner_id'],
                'status' => $data['status'],
            ])->save();

            return $risk;
        });
    }
}
