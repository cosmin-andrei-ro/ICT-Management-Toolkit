<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'menu' => 'Management',
    'title' => 'Management overview',
    'intro' => 'Every indicator of every module, now and over the last 90 days.',
    'trend_of' => 'Last days of :metric',
    'columns' => [
        'indicator' => 'Indicator',
        'now' => 'Now',
        'status' => 'Status',
        'change' => 'Change in 30 days',
        'trend' => 'Last 90 days',
    ],
    'report' => [
        'month' => 'Monthly report',
        'so_far' => 'so far',
        'open' => 'Open the report',
        'title' => 'Management report — :month',
        'as_of' => 'Figures as of :date',
        'printed' => 'Printed :date',
        'summary' => 'Summary',
        'summary_help' => 'Indicators by status at the end of the month. Indicators without thresholds are not counted.',
        'end_of_month' => 'End of month',
        'month_before' => 'Month before',
        'twelve_months' => 'Last 12 months',
        'footnote' => 'Values are recorded once a day; "—" means no value was recorded. The lines show the last value of each day over the twelve months to the end of the month.',
    ],
    'subscriptions' => [
        'title' => 'Monthly report by email',
        'help' => 'On the first day of each month, these people receive the report of the month that ended. Only people who may see this overview can be added; someone who loses that permission stops receiving it.',
        'none' => 'Nobody receives the report yet.',
        'person' => 'Person',
        'choose' => 'Choose a person…',
        'add' => 'Add',
        'remove' => 'Remove',
        'not_eligible' => 'This person cannot receive the report: they must be an active member who may see the management overview.',
    ],
    'mail' => [
        'subject' => 'Management report — :month — :organization',
        'intro' => 'The management report for :month is ready.',
        'alert' => '{1} One indicator needs action now.|[2,*] :count indicators need action now.',
        'warning' => '{1} One indicator needs attention.|[2,*] :count indicators need attention.',
        'action' => 'Open the report',
    ],
];
