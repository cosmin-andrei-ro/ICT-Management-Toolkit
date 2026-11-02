<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Application\Catalog;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Risk\Domain\Models\CatalogEntry;

/**
 * The starter catalog of threats and vulnerabilities (owner's decision,
 * inspired by ISO/IEC 27005 annex A and ENISA's threat landscape), created on
 * first use; the risk managers own it afterwards.
 */
final readonly class ProvisionCatalog
{
    public function __construct(
        private ConnectionInterface $db,
        private OrganizationContext $context,
    ) {}

    public function handle(): void
    {
        if (CatalogEntry::query()->exists()) {
            return;
        }

        $organizationId = $this->context->requireId();
        $entries = trans('risk::catalog.defaults');

        $this->db->transaction(function () use ($organizationId, $entries): void {
            foreach (is_array($entries) ? $entries : [] as $kind => $names) {
                foreach (is_array($names) ? $names : [] as $name => $description) {
                    $this->db->table('risk_catalog_entries')->insertOrIgnore([
                        'id' => (string) Str::uuid7(),
                        'organization_id' => $organizationId,
                        'kind' => $kind,
                        'name' => $name,
                        'description' => is_string($description) ? $description : null,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }
}
