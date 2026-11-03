<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Application\PersonalData;

use Generator;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\PersonalData\PersonalDataEntry;
use Modules\Risk\Domain\Models\Acceptance;
use Modules\Risk\Domain\Models\Risk;
use Modules\Risk\Domain\Models\RiskAction;

/**
 * Risks and treatment actions a person owns, and the acceptances they asked for or decided.
 */
final readonly class RiskPersonalData
{
    /**
     * @return Generator<PersonalDataEntry>
     */
    public function __invoke(User $person): Generator
    {
        foreach (Risk::query()->where('owner_id', $person->id)->orderBy('reference')->get() as $risk) {
            yield new PersonalDataEntry(__('risk::personal_data.records.risk'), $risk->code(), $risk->title, __('risk::personal_data.roles.owner'), $risk->created_at);
        }

        foreach (RiskAction::query()->with('risk')->where('owner_id', $person->id)->orderBy('created_at')->get() as $action) {
            yield new PersonalDataEntry(__('risk::personal_data.records.action'), $action->risk?->code(), $action->title, __('risk::personal_data.roles.owner'), $action->created_at);
        }

        foreach (Acceptance::query()->with('risk')->where(fn ($query) => $query->where('requested_by', $person->id)->orWhere('decided_by', $person->id))->orderBy('requested_at')->get() as $acceptance) {
            $requested = $acceptance->requested_by === $person->id;
            yield new PersonalDataEntry(__('risk::personal_data.records.acceptance'), $acceptance->risk->code(), $acceptance->risk->title,
                __($requested ? 'risk::personal_data.roles.requester' : 'risk::personal_data.roles.decider'), $requested ? $acceptance->requested_at : $acceptance->decided_at, $requested ? $acceptance->justification : $acceptance->comment);
        }
    }
}
