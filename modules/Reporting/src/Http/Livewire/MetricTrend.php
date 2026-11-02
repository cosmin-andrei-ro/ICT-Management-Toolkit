<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Reporting\Http\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Core\Domain\Metrics\Metric;
use Modules\Core\Domain\Metrics\Metrics;
use Modules\Core\Domain\Models\User;
use Modules\Reporting\Application\Trends;

/**
 * One indicator over 30, 90 or 365 days (ADR 0045), for the people who may
 * see the indicator.
 */
#[Layout('core::components.layouts.app')]
final class MetricTrend extends Component
{
    public string $metricKey = '';

    #[Url]
    public int $days = 30;

    public function mount(string $metric): void
    {
        $this->metricKey = $metric;
        $this->metric();
    }

    public function period(int $days): void
    {
        $this->days = in_array($days, Trends::PERIODS, true) ? $days : 30;
    }

    public function render(Metrics $metrics, Trends $trends): View
    {
        $metric = $this->metric();
        $days = in_array($this->days, Trends::PERIODS, true) ? $this->days : 30;
        $series = $trends->series([$metric->key], $days)[$metric->key];
        $recorded = array_values(array_filter($series, fn (?float $value): bool => $value !== null));
        $current = $metrics->current($metric->key);

        return view('reporting::livewire.metric-trend', [
            'metric' => $metric,
            'period' => $days,
            'series' => $series,
            'current' => $current,
            'status' => $metric->status($current),
            'summary' => $recorded === [] ? null : [
                'first' => $recorded[0],
                'lowest' => min($recorded),
                'highest' => max($recorded),
                'average' => array_sum($recorded) / count($recorded),
            ],
        ])->title(__($metric->label));
    }

    private function metric(): Metric
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);
        $metric = app(Metrics::class)->metric($this->metricKey);
        abort_if($metric === null, 404);
        abort_unless($user->can($metric->permission), 403);

        return $metric;
    }
}
