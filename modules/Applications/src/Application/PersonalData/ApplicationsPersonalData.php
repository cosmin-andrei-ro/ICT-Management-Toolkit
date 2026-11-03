<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Application\PersonalData;

use Generator;
use Modules\Applications\Domain\Models\Application;
use Modules\Applications\Domain\Models\SheetApproval;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\PersonalData\PersonalDataEntry;

/**
 * Applications a person owns or stands in for, and the documentation sheets they approved.
 */
final readonly class ApplicationsPersonalData
{
    /**
     * @return Generator<PersonalDataEntry>
     */
    public function __invoke(User $person): Generator
    {
        $applications = Application::query()->where(fn ($query) => $query->where('business_owner_id', $person->id)->orWhere('technical_owner_id', $person->id)->orWhere('deputy_id', $person->id))->orderBy('code');

        foreach ($applications->get() as $application) {
            yield new PersonalDataEntry(__('applications::personal_data.records.application'), $application->code, $application->name, PersonalDataEntry::roles(
                $application->business_owner_id === $person->id ? __('applications::personal_data.roles.business_owner') : null,
                $application->technical_owner_id === $person->id ? __('applications::personal_data.roles.technical_owner') : null,
                $application->deputy_id === $person->id ? __('applications::personal_data.roles.deputy') : null,
            ), $application->created_at);
        }

        foreach (SheetApproval::query()->with('sheet.application')->where('user_id', $person->id)->orderBy('approved_at')->get() as $approval) {
            yield new PersonalDataEntry(__('applications::personal_data.records.approval'), $approval->sheet?->application->code, $approval->sheet->application->name ?? '',
                __('applications::personal_data.roles.approver'), $approval->approved_at, $approval->comment);
        }
    }
}
