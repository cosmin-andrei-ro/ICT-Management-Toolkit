<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Application\PersonalData;

use Generator;
use Modules\Audit\Domain\Models\Audit;
use Modules\Audit\Domain\Models\Control;
use Modules\Audit\Domain\Models\ControlTest;
use Modules\Audit\Domain\Models\Finding;
use Modules\Audit\Domain\Models\FindingAction;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\PersonalData\PersonalDataEntry;

/**
 * Controls, findings and actions a person owns, the tests they ran and the audits they led.
 */
final readonly class AuditPersonalData
{
    /**
     * @return Generator<PersonalDataEntry>
     */
    public function __invoke(User $person): Generator
    {
        foreach (Control::query()->where('owner_id', $person->id)->orderBy('number')->get() as $control) {
            yield new PersonalDataEntry(__('audit::personal_data.records.control'), $control->code(), $control->name, __('audit::personal_data.roles.owner'), $control->created_at);
        }

        foreach (ControlTest::query()->with('control')->where('tester_id', $person->id)->orderBy('tested_on')->get() as $test) {
            yield new PersonalDataEntry(__('audit::personal_data.records.test'), $test->control?->code(), $test->control->name ?? '', __('audit::personal_data.roles.tester'), $test->tested_on, $test->notes);
        }

        foreach (Audit::query()->where('lead_auditor_id', $person->id)->orderBy('starts_on')->get() as $audit) {
            yield new PersonalDataEntry(__('audit::personal_data.records.audit'), $audit->code(), $audit->title, __('audit::personal_data.roles.lead'), $audit->starts_on);
        }

        foreach (Finding::query()->where(fn ($query) => $query->where('owner_id', $person->id)->orWhere('submitted_by', $person->id)->orWhere('verified_by', $person->id))->orderBy('number')->get() as $finding) {
            yield new PersonalDataEntry(__('audit::personal_data.records.finding'), $finding->code(), $finding->title, PersonalDataEntry::roles(
                $finding->owner_id === $person->id ? __('audit::personal_data.roles.owner') : null,
                $finding->submitted_by === $person->id ? __('audit::personal_data.roles.submitter') : null,
                $finding->verified_by === $person->id ? __('audit::personal_data.roles.verifier') : null,
            ), $finding->created_at);
        }

        foreach (FindingAction::query()->with('finding')->where('owner_id', $person->id)->orderBy('created_at')->get() as $action) {
            yield new PersonalDataEntry(__('audit::personal_data.records.action'), $action->finding?->code(), $action->title, __('audit::personal_data.roles.owner'), $action->created_at);
        }
    }
}
