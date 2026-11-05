<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Pam\Application\Metrics;

use Modules\Core\Domain\Metrics\Metric;
use Modules\Core\Domain\Metrics\MetricSource;
use Modules\Pam\Domain\Enums\RequestStatus;
use Modules\Pam\Domain\Models\AccessRequest;
use Modules\Pam\Domain\Models\PrivilegedAccount;

/**
 * Indicators of the vault for the dashboards. Counts only: no account names.
 */
final class PamMetrics
{
    public static function source(): MetricSource
    {
        $view = 'pam.audit.view';

        return new MetricSource('pam', 'pam::accounts.metrics.group', [
            new Metric('pam.rotation_overdue', 'pam::accounts.metrics.rotation_overdue', $view, warnAt: 1, route: 'pam.accounts.index'),
            new Metric('pam.review_overdue', 'pam::accounts.metrics.review_overdue', $view, warnAt: 1, route: 'pam.accounts.index'),
            new Metric('pam.requests_waiting', 'pam::accounts.metrics.requests_waiting', $view, route: 'pam.requests'),
        ], self::values(...));
    }

    /**
     * @return array<string, int|float|null>
     */
    public static function values(): array
    {
        $accounts = PrivilegedAccount::query()->where(fn ($query) => $query->whereNotNull('rotation_days')->orWhereNotNull('review_days'))->get();

        return [
            'pam.rotation_overdue' => $accounts->filter(fn (PrivilegedAccount $account): bool => $account->rotationDueOn()?->isPast() === true)->count(),
            'pam.review_overdue' => $accounts->filter(fn (PrivilegedAccount $account): bool => $account->reviewDueOn()?->isPast() === true)->count(),
            'pam.requests_waiting' => AccessRequest::query()->where('status', RequestStatus::Pending)->count(),
        ];
    }
}
