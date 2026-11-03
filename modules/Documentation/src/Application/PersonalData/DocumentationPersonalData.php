<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Documentation\Application\PersonalData;

use Generator;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\PersonalData\PersonalDataEntry;
use Modules\Documentation\Domain\Models\PageVersion;
use Modules\Documentation\Domain\Models\Space;

/**
 * Pages a person wrote or approved, and the spaces they own.
 */
final readonly class DocumentationPersonalData
{
    /**
     * @return Generator<PersonalDataEntry>
     */
    public function __invoke(User $person): Generator
    {
        foreach (Space::query()->whereHas('owners', fn ($query) => $query->whereKey($person->id))->orderBy('name')->get() as $space) {
            yield new PersonalDataEntry(__('documentation::personal_data.records.space'), null, $space->name, __('documentation::personal_data.roles.owner'));
        }

        $versions = PageVersion::query()->whereHas('page')->where(fn ($query) => $query->where('author_id', $person->id)->orWhere('approved_by', $person->id))->orderBy('created_at');

        foreach ($versions->lazy(200) as $version) {
            yield new PersonalDataEntry(__('documentation::personal_data.records.version'), 'v'.$version->version, $version->title,
                __($version->author_id === $person->id ? 'documentation::personal_data.roles.author' : 'documentation::personal_data.roles.approver'), $version->created_at, $version->summary);
        }
    }
}
