<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Reporting\Http\Controllers\Api;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Domain\Metrics\Metrics;
use Modules\Core\Domain\Models\User;
use Modules\Reporting\Domain\Models\MetricSnapshot;

/**
 * The indicators of every module, read-only (ADR 0065): those the user may
 * see, their current value and status, and their daily history.
 */
final readonly class MetricController
{
    public function __construct(private Metrics $metrics) {}

    /**
     * The indicators the user may see, by module, with their current value and status (good, warning, alert, neutral).
     */
    public function index(Request $request): JsonResponse
    {
        $groups = [];

        foreach ($this->metrics->visibleTo($this->user($request)) as $group) {
            foreach ($group['metrics'] as $entry) {
                $groups[] = [
                    'key' => $entry['metric']->key, 'label' => __($entry['metric']->label), 'unit' => $entry['metric']->unit->value,
                    'value' => $entry['value'], 'status' => $entry['status']->value, 'lower_is_better' => $entry['metric']->lowerIsBetter,
                ];
            }
        }

        return response()->json(['data' => $groups]);
    }

    /**
     * An indicator's daily values. `?days=` from 1 to 730 (90 by default).
     */
    public function history(Request $request, string $key): JsonResponse
    {
        $metric = $this->metrics->metric($key);
        abort_if($metric === null || ! $this->user($request)->can($metric->permission), 404);
        $request->validate(['days' => ['sometimes', 'integer', 'between:1,730']]);
        $since = CarbonImmutable::today()->subDays($request->integer('days', 90) - 1)->toDateString();

        return response()->json(['data' => MetricSnapshot::query()->where('metric_key', $key)->where('taken_on', '>=', $since)->orderBy('taken_on')->get()
            ->map(fn (MetricSnapshot $snapshot): array => ['date' => $snapshot->taken_on->toDateString(), 'value' => $snapshot->value !== null ? (float) $snapshot->value : null])->all()]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
