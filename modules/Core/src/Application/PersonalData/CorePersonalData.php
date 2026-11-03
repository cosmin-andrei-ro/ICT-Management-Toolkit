<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\PersonalData;

use Generator;
use Modules\Core\Domain\Models\Contract;
use Modules\Core\Domain\Models\Department;
use Modules\Core\Domain\Models\SecurityKey;
use Modules\Core\Domain\Models\Team;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\PersonalData\PersonalDataEntry;

/**
 * The organization chart, contracts and security keys a person is named in.
 */
final readonly class CorePersonalData
{
    /**
     * @return Generator<PersonalDataEntry>
     */
    public function __invoke(User $person): Generator
    {
        foreach (Department::query()->where('manager_id', $person->id)->orderBy('name')->get() as $department) {
            yield new PersonalDataEntry(__('core::personal_data.records.department'), $department->code, $department->name, __('core::personal_data.roles.manager'));
        }

        foreach (Team::query()->where('lead_id', $person->id)->orWhereHas('members', fn ($query) => $query->whereKey($person->id))->orderBy('name')->get() as $team) {
            yield new PersonalDataEntry(__('core::personal_data.records.team'), null, $team->name, __($team->lead_id === $person->id ? 'core::personal_data.roles.lead' : 'core::personal_data.roles.member'));
        }

        foreach (Contract::query()->where('owner_id', $person->id)->orderBy('title')->get() as $contract) {
            yield new PersonalDataEntry(__('core::personal_data.records.contract'), $contract->reference, $contract->title, __('core::personal_data.roles.owner'), $contract->start_date);
        }

        foreach (SecurityKey::query()->where('user_id', $person->id)->orderBy('created_at')->get() as $key) {
            yield new PersonalDataEntry(__('core::personal_data.records.security_key'), null, $key->name, __('core::personal_data.roles.holder'), $key->created_at);
        }
    }
}
