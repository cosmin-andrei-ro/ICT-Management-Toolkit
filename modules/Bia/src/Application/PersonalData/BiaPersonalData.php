<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Application\PersonalData;

use Generator;
use Modules\Bia\Domain\Models\Assessment;
use Modules\Bia\Domain\Models\BusinessProcess;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\PersonalData\PersonalDataEntry;

/**
 * Business processes a person owns or stands in for, and the assessments they submitted or decided.
 */
final readonly class BiaPersonalData
{
    /**
     * @return Generator<PersonalDataEntry>
     */
    public function __invoke(User $person): Generator
    {
        foreach (BusinessProcess::query()->where(fn ($query) => $query->where('owner_id', $person->id)->orWhere('deputy_id', $person->id))->orderBy('name')->get() as $process) {
            yield new PersonalDataEntry(__('bia::personal_data.records.process'), null, $process->name,
                __($process->owner_id === $person->id ? 'bia::personal_data.roles.owner' : 'bia::personal_data.roles.deputy'), $process->created_at);
        }

        foreach (Assessment::query()->with('process')->where(fn ($query) => $query->where('submitted_by', $person->id)->orWhere('decided_by', $person->id))->orderBy('created_at')->get() as $assessment) {
            yield new PersonalDataEntry(__('bia::personal_data.records.assessment'), 'v'.$assessment->version, $assessment->process->name,
                __($assessment->decided_by === $person->id ? 'bia::personal_data.roles.decider' : 'bia::personal_data.roles.submitter'), $assessment->decided_at ?? $assessment->submitted_at, $assessment->decided_by === $person->id ? $assessment->decision_comment : null);
        }
    }
}
