<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Reporting\Application\PersonalData;

use Generator;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\PersonalData\PersonalDataEntry;
use Modules\Reporting\Domain\Models\DashboardPreference;
use Modules\Reporting\Domain\Models\ReportSubscription;

/**
 * A person's dashboard choices and their subscription to the monthly report.
 */
final readonly class ReportingPersonalData
{
    /**
     * @return Generator<PersonalDataEntry>
     */
    public function __invoke(User $person): Generator
    {
        foreach (ReportSubscription::query()->where('user_id', $person->id)->get() as $subscription) {
            yield new PersonalDataEntry(__('reporting::personal_data.records.subscription'), null, __('reporting::personal_data.monthly_report'), __('reporting::personal_data.roles.recipient'), $subscription->created_at);
        }

        foreach (DashboardPreference::query()->where('user_id', $person->id)->get() as $preference) {
            yield new PersonalDataEntry(__('reporting::personal_data.records.preference'), null, __('reporting::personal_data.dashboard'), __('reporting::personal_data.roles.owner'), $preference->updated_at,
                json_encode(['hidden' => $preference->hidden_metrics, 'order' => $preference->source_order], JSON_UNESCAPED_SLASHES) ?: null);
        }
    }
}
