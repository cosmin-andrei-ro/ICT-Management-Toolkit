<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Reporting\Http\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Modules\Core\Domain\Metrics\Metrics;
use Modules\Core\Domain\Models\User;
use Modules\Reporting\Application\Actions\CustomiseDashboard;
use Modules\Reporting\Application\Trends;
use Modules\Reporting\Domain\Models\DashboardPreference;

/**
 * The home page dashboard (ADR 0044): the indicators of every module the
 * person may see, grouped by module, which they can hide and reorder.
 */
final class Dashboard extends Component
{
    public bool $customising = false;

    public function toggleCustomising(): void
    {
        $this->customising = ! $this->customising;
    }

    public function hide(string $metric, CustomiseDashboard $customise): void
    {
        $customise->hide($this->actor(), $metric);
    }

    public function show(string $metric, CustomiseDashboard $customise): void
    {
        $customise->show($this->actor(), $metric);
    }

    public function moveUp(string $source, CustomiseDashboard $customise): void
    {
        $customise->move($this->actor(), $source, -1);
    }

    public function moveDown(string $source, CustomiseDashboard $customise): void
    {
        $customise->move($this->actor(), $source, 1);
    }

    public function restoreDefaults(CustomiseDashboard $customise): void
    {
        $customise->reset($this->actor());
    }

    public function render(Metrics $metrics, CustomiseDashboard $customise, Trends $trends): View
    {
        $user = $this->actor();
        $preference = DashboardPreference::of($user);
        $sources = $customise->order($user, $preference);
        $order = array_flip($sources);
        $groups = $metrics->visibleTo($user);
        usort($groups, fn (array $a, array $b): int => ($order[$a['source']->key] ?? PHP_INT_MAX) <=> ($order[$b['source']->key] ?? PHP_INT_MAX));

        if (! $this->customising) {
            $groups = array_values(array_filter(array_map(fn (array $group): array => [
                ...$group,
                'metrics' => array_values(array_filter($group['metrics'], fn (array $row): bool => ! in_array($row['metric']->key, $preference->hidden_metrics, true))),
            ], $groups), fn (array $group): bool => $group['metrics'] !== []));
        }

        $keys = [];

        foreach ($groups as $group) {
            foreach ($group['metrics'] as $row) {
                $keys[] = $row['metric']->key;
            }
        }

        return view('reporting::livewire.dashboard', [
            'trends' => $trends->series($keys, 30),
            'available' => $sources !== [],
            'groups' => $groups,
            'hidden' => $preference->hidden_metrics,
        ]);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
