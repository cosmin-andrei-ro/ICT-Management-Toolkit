<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Links;

/**
 * Physical assets of the current organization for other modules (DCIM racks):
 * implemented by asset management; when it is disabled, none.
 */
interface PhysicalAssets
{
    /**
     * @param  list<string>  $ids
     * @return array<string, PhysicalAsset> By id; unknown ids are left out.
     */
    public function find(array $ids): array;

    /**
     * Assets still in service matching a tag, name, serial number or model.
     *
     * @return list<PhysicalAsset>
     */
    public function search(string $term, int $limit = 20): array;
}
