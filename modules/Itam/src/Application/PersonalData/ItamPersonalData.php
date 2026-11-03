<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Application\PersonalData;

use Generator;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\PersonalData\PersonalDataEntry;
use Modules\Itam\Domain\Models\AssetAssignment;
use Modules\Itam\Domain\Models\LicenseAssignment;

/**
 * Equipment handed to a person (and given back), and the licences assigned to them.
 */
final readonly class ItamPersonalData
{
    /**
     * Handovers keep that they were acknowledged, not from which address.
     */
    public static function erase(User $person, string $email): void
    {
        AssetAssignment::query()->where('user_id', $person->id)->whereNotNull('acknowledged_ip')->update(['acknowledged_ip' => 'anonymized']);
    }

    /**
     * @return Generator<PersonalDataEntry>
     */
    public function __invoke(User $person): Generator
    {
        foreach (AssetAssignment::query()->with('asset')->where('user_id', $person->id)->orderBy('assigned_at')->get() as $assignment) {
            yield new PersonalDataEntry(__('itam::personal_data.records.asset'), $assignment->asset->asset_tag, $assignment->asset->name ?? $assignment->asset->asset_tag ?? '', __('itam::personal_data.roles.holder'), $assignment->assigned_at,
                $assignment->returned_at !== null ? PersonalDataEntry::text(__('itam::personal_data.returned', ['date' => $assignment->returned_at->toDateString()])) : $assignment->notes);
        }

        foreach (LicenseAssignment::query()->with('license')->where('user_id', $person->id)->orderBy('assigned_at')->get() as $assignment) {
            yield new PersonalDataEntry(__('itam::personal_data.records.license'), null, $assignment->license->name, __('itam::personal_data.roles.holder'), $assignment->assigned_at);
        }
    }
}
