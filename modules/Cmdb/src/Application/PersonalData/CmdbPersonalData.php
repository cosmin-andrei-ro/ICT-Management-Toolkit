<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Cmdb\Application\PersonalData;

use Generator;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\PersonalData\PersonalDataEntry;

/**
 * Configuration items a person owns.
 */
final readonly class CmdbPersonalData
{
    /**
     * @return Generator<PersonalDataEntry>
     */
    public function __invoke(User $person): Generator
    {
        foreach (ConfigurationItem::query()->where('owner_id', $person->id)->orderBy('name')->get() as $item) {
            yield new PersonalDataEntry(__('cmdb::personal_data.records.item'), $item->identifier, $item->name, __('cmdb::personal_data.roles.owner'), $item->created_at);
        }
    }
}
