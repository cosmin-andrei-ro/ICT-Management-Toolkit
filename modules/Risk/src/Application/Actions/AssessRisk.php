<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Risk\Domain\Models\CatalogEntry;
use Modules\Risk\Domain\Models\Risk;

/**
 * The owner's assessment of a risk before treatment: likelihood and impact on
 * the organization's scale, the threats and vulnerabilities of the catalog
 * involved, and the controls already in place. Audited on the risk.
 */
final readonly class AssessRisk
{
    public function __construct(
        private ConnectionInterface $db,
        private AuditTrail $trail,
    ) {}

    /**
     * @param  list<string>  $entryIds  Catalog entries of the organization.
     *
     * @throws InvalidArgumentException for a score outside 1–5 or an unknown catalog entry
     */
    public function handle(Risk $risk, ?int $likelihood, ?int $impact, array $entryIds, ?string $controls): void
    {
        if (($likelihood === null) !== ($impact === null) || ($likelihood !== null && ($likelihood < 1 || $likelihood > 5 || $impact < 1 || $impact > 5))) {
            throw new InvalidArgumentException('Likelihood and impact go together, from 1 to 5.');
        }

        $entryIds = array_values(array_unique($entryIds));

        // Scoped lookup: entries of another organization are not found.
        if (CatalogEntry::query()->whereKey($entryIds)->count() !== count($entryIds)) {
            throw new InvalidArgumentException('Unknown catalog entry.');
        }

        $this->db->transaction(function () use ($risk, $likelihood, $impact, $entryIds, $controls): void {
            $controls = $controls !== null ? trim($controls) : '';
            $before = $risk->catalogEntries()->pluck('name')->sort()->values()->all();

            $risk->forceFill([
                'inherent_likelihood' => $likelihood,
                'inherent_impact' => $impact,
                'existing_controls' => $controls !== '' ? $controls : null,
            ])->save();

            $risk->catalogEntries()->syncWithPivotValues($entryIds, ['organization_id' => $risk->organization_id]);
            $after = $risk->catalogEntries()->pluck('name')->sort()->values()->all();

            if ($before !== $after) {
                $this->trail->record(AuditEvent::Updated, $risk, ['catalog' => $before], ['catalog' => $after]);
            }
        });
    }
}
