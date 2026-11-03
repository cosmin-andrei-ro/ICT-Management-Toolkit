<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Application\PersonalData;

use Generator;
use Modules\Bcm\Domain\Models\Activation;
use Modules\Bcm\Domain\Models\Exercise;
use Modules\Bcm\Domain\Models\Plan;
use Modules\Bcm\Domain\Models\TeamMember;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\PersonalData\PersonalDataEntry;

/**
 * Continuity plans a person owns or is in the team of, the exercises they led and the activations they declared.
 */
final readonly class BcmPersonalData
{
    /**
     * @return Generator<PersonalDataEntry>
     */
    public function __invoke(User $person): Generator
    {
        foreach (Plan::query()->where(fn ($query) => $query->where('owner_id', $person->id)->orWhere('deputy_id', $person->id))->orderBy('name')->get() as $plan) {
            yield new PersonalDataEntry(__('bcm::personal_data.records.plan'), null, $plan->name, __($plan->owner_id === $person->id ? 'bcm::personal_data.roles.owner' : 'bcm::personal_data.roles.deputy'), $plan->created_at);
        }

        foreach (TeamMember::query()->with('version.plan')->where('user_id', $person->id)->get() as $member) {
            yield new PersonalDataEntry(__('bcm::personal_data.records.team'), $member->version !== null ? 'v'.$member->version->version : null, $member->version->plan->name ?? '', $member->role, null, $member->responsibilities);
        }

        foreach (Exercise::query()->where('facilitator_id', $person->id)->orderBy('scheduled_on')->get() as $exercise) {
            yield new PersonalDataEntry(__('bcm::personal_data.records.exercise'), null, $exercise->title, __('bcm::personal_data.roles.facilitator'), $exercise->scheduled_on);
        }

        foreach (Activation::query()->with('plan')->where(fn ($query) => $query->where('activated_by', $person->id)->orWhere('closed_by', $person->id))->orderBy('activated_at')->get() as $activation) {
            yield new PersonalDataEntry(__('bcm::personal_data.records.activation'), null, $activation->plan->name,
                __($activation->activated_by === $person->id ? 'bcm::personal_data.roles.activated' : 'bcm::personal_data.roles.closed'), $activation->activated_at, $activation->reason);
        }
    }
}
