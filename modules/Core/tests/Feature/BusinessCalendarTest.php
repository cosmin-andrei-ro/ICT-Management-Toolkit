<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Modules\Core\Domain\Calendar\BusinessCalendars;
use Modules\Core\Domain\Calendar\BusinessTimeCalculator;
use Modules\Core\Domain\Models\BusinessCalendar;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    app(OrganizationContext::class)->set($this->organization);
});

it('falls back to the built-in schedule when the organization has no calendar', function (): void {
    $schedule = app(BusinessCalendars::class)->forCurrentOrganization();

    expect($schedule->timezone->getName())->toBe('Europe/Bucharest')
        ->and($schedule->isHoliday('2026-12-01'))->toBeTrue()
        ->and($schedule->weeklyHours[6] ?? [])->toBe([]);
});

it('uses the organization default calendar with its extra holidays', function (): void {
    $calendar = BusinessCalendar::factory()->create(['weekly_hours' => [1 => [['08:00', '16:00']]]]);
    $calendar->holidays()->create(['date' => '2026-11-09', 'name' => 'Company day off']);

    $schedule = app(BusinessCalendars::class)->forCurrentOrganization();
    $due = app(BusinessTimeCalculator::class)->addMinutes($schedule, new DateTimeImmutable('2026-11-02 15:00', $schedule->timezone), 120);

    // Monday 15:00 + 2h with Monday-only 08–16 hours and 9 Nov off: next Monday 16 Nov 09:00.
    expect($due->format('Y-m-d H:i'))->toBe('2026-11-16 09:00');
});

it('never stores an invalid calendar', function (): void {
    BusinessCalendar::factory()->create(['weekly_hours' => [1 => [['18:00', '09:00']]]]);
})->throws(InvalidArgumentException::class);

it('keeps a single default calendar per organization', function (): void {
    $first = BusinessCalendar::factory()->create(['name' => 'Office']);
    BusinessCalendar::factory()->create(['name' => 'Support 24/7']);

    expect(BusinessCalendar::query()->where('is_default', true)->pluck('name')->all())->toBe(['Support 24/7'])
        ->and($first->fresh()?->is_default)->toBeFalse();
});

it('ignores calendars of other organizations', function (): void {
    app(OrganizationContext::class)->runAs(Organization::factory()->create(), fn () => BusinessCalendar::factory()->create(['timezone' => 'UTC']));

    expect(app(BusinessCalendars::class)->forCurrentOrganization()->timezone->getName())->toBe('Europe/Bucharest');
});
