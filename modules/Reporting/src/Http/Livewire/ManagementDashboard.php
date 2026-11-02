<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Reporting\Http\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Domain\Metrics\Metrics;
use Modules\Core\Domain\Models\User;
use Modules\Reporting\Application\Actions\ManageReportSubscriptions;
use Modules\Reporting\Application\MonthlyReport;
use Modules\Reporting\Application\Trends;
use Modules\Reporting\Domain\Models\ReportSubscription;

/**
 * Every indicator of every module in one place (ADR 0046), for management:
 * figures only, so it needs no permission on the modules themselves; links
 * to the lists behind the figures still follow them.
 */
#[Layout('core::components.layouts.app')]
final class ManagementDashboard extends Component
{
    public string $month = '';

    public string $subscriber = '';

    public function mount(): void
    {
        $this->authorizeView();
        $this->month = (MonthlyReport::months()[1] ?? MonthlyReport::months()[0])->format('Y-m');
    }

    public function subscribe(ManageReportSubscriptions $subscriptions): void
    {
        $this->resetErrorBag();
        $subscriptions->subscribe($this->actor(), $this->subscriber);
        $this->subscriber = '';
    }

    public function unsubscribe(string $subscription, ManageReportSubscriptions $subscriptions): void
    {
        $subscriptions->unsubscribe($this->actor(), $subscription);
    }

    public function render(Metrics $metrics, Trends $trends, ManageReportSubscriptions $subscriptions): View
    {
        $user = $this->authorizeView();
        $groups = $metrics->overview();
        $keys = [];

        foreach ($groups as $group) {
            foreach ($group['metrics'] as $row) {
                $keys[] = $row['metric']->key;
            }
        }

        $series = $trends->series($keys, 90);
        $manages = $user->can(ManageReportSubscriptions::MANAGE);

        return view('reporting::livewire.management', [
            'user' => $user,
            'groups' => $groups,
            'series' => $series,
            // The value 30 days ago, where one was recorded.
            'before' => array_map(fn (array $values): ?float => array_values($values)[59] ?? null, $series),
            'months' => MonthlyReport::months(),
            'manages' => $manages,
            'subscriptions' => $manages ? ReportSubscription::query()->with('user')->get()->sortBy('user.name') : collect(),
            'candidates' => $manages ? $subscriptions->candidates() : collect(),
        ])->title(__('reporting::management.title'));
    }

    private function authorizeView(): User
    {
        $user = $this->actor();
        abort_unless($user->can(ManageReportSubscriptions::VIEW), 403);

        return $user;
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
