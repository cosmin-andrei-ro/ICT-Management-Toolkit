<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Reporting\Application\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Metrics\Metric;
use Modules\Core\Domain\Metrics\Metrics;
use Modules\Core\Domain\Models\User;
use Modules\Reporting\Domain\Models\DashboardPreference;

/**
 * Hides, shows and reorders what a person sees on their dashboard. Only
 * indicators and groups the person may see are accepted, so the stored
 * setting never names anything else.
 */
final readonly class CustomiseDashboard
{
    public function __construct(private Metrics $metrics) {}

    public function hide(User $user, string $metricKey): void
    {
        $this->visibleMetric($user, $metricKey);
        $preference = DashboardPreference::of($user);
        $preference->hidden_metrics = array_values(array_unique([...$preference->hidden_metrics, $metricKey]));
        $preference->save();
    }

    public function show(User $user, string $metricKey): void
    {
        $this->visibleMetric($user, $metricKey);
        $preference = DashboardPreference::of($user);
        $preference->hidden_metrics = array_values(array_diff($preference->hidden_metrics, [$metricKey]));
        $preference->save();
    }

    /**
     * @param  -1|1  $direction  -1 moves the group up, 1 down.
     */
    public function move(User $user, string $sourceKey, int $direction): void
    {
        $preference = DashboardPreference::of($user);
        $order = $this->order($user, $preference);
        $position = array_search($sourceKey, $order, true);

        if ($position === false) {
            throw ValidationException::withMessages(['source' => __('reporting::dashboard.unknown')]);
        }

        $target = $position + $direction;

        if (! isset($order[$target])) {
            return;
        }

        [$order[$position], $order[$target]] = [$order[$target], $order[$position]];
        $preference->source_order = array_values($order);
        $preference->save();
    }

    public function reset(User $user): void
    {
        DashboardPreference::query()->where('user_id', $user->id)->delete();
    }

    /**
     * Keys of the groups the person may see, in the order they chose.
     *
     * @return list<string>
     */
    public function order(User $user, DashboardPreference $preference): array
    {
        $visible = array_keys(array_filter(
            $this->metrics->sources(),
            fn ($source): bool => array_any($source->metrics, fn (Metric $metric): bool => $user->can($metric->permission)),
        ));
        $chosen = array_values(array_intersect($preference->source_order, $visible));

        return [...$chosen, ...array_values(array_diff($visible, $chosen))];
    }

    private function visibleMetric(User $user, string $key): void
    {
        $metric = $this->metrics->metric($key);

        if ($metric === null || ! $user->can($metric->permission)) {
            throw ValidationException::withMessages(['metric' => __('reporting::dashboard.unknown')]);
        }
    }
}
