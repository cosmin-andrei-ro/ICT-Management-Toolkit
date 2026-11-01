<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Livewire\Concerns;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Validation\Rule;
use Modules\Ticketing\Application\Changes\ChangePlan;
use Modules\Ticketing\Domain\Changes\ChangeRisk;
use Modules\Ticketing\Domain\Models\ChangeDetails;

/**
 * Plan fields shared by "new change" and the change page. The window is entered
 * in the display time zone and converted by ChangePlan.
 */
trait EditsChangePlan
{
    public string $risk = 'medium';

    public string $implementationPlan = '';

    public string $rollbackPlan = '';

    public string $testPlan = '';

    /** datetime-local value, display time zone */
    public string $plannedStart = '';

    public string $plannedEnd = '';

    /**
     * @return array<string, mixed>
     */
    protected function planRules(): array
    {
        return [
            'risk' => ['required', Rule::enum(ChangeRisk::class)],
            'implementationPlan' => ['nullable', 'string', 'max:'.ChangePlan::MAX_LENGTH],
            'rollbackPlan' => ['nullable', 'string', 'max:'.ChangePlan::MAX_LENGTH],
            'testPlan' => ['nullable', 'string', 'max:'.ChangePlan::MAX_LENGTH],
            'plannedStart' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'plannedEnd' => ['nullable', 'date_format:Y-m-d\TH:i', 'after:plannedStart'],
        ];
    }

    protected function toPlan(): ChangePlan
    {
        return new ChangePlan(
            ChangeRisk::from($this->risk),
            $this->implementationPlan,
            $this->rollbackPlan,
            $this->testPlan,
            $this->parseLocal($this->plannedStart),
            $this->parseLocal($this->plannedEnd),
        );
    }

    protected function fillPlan(ChangeDetails $details): void
    {
        $this->risk = $details->risk->value;
        $this->implementationPlan = $details->implementation_plan ?? '';
        $this->rollbackPlan = $details->rollback_plan ?? '';
        $this->testPlan = $details->test_plan ?? '';
        $this->plannedStart = $this->formatLocal($details->planned_start);
        $this->plannedEnd = $this->formatLocal($details->planned_end);
    }

    private function parseLocal(string $value): ?CarbonImmutable
    {
        return $value !== '' ? CarbonImmutable::createFromFormat('Y-m-d\TH:i', $value, self::displayTimezone()) ?: null : null;
    }

    private function formatLocal(?CarbonInterface $instant): string
    {
        return $instant !== null ? $instant->copy()->setTimezone(self::displayTimezone())->format('Y-m-d\TH:i') : '';
    }

    private static function displayTimezone(): string
    {
        $timezone = config('app.display_timezone');

        return is_string($timezone) && $timezone !== '' ? $timezone : 'UTC';
    }
}
