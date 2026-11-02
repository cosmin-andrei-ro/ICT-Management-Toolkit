<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Modules\Bia\Domain\Duration;
use Modules\Bia\Domain\Enums\Tier;
use Modules\Bia\Domain\ImpactMatrix;

$horizons = [['id' => 'week', 'minutes' => 10080], ['id' => 'h4', 'minutes' => 240], ['id' => 'day', 'minutes' => 1440]];

it('takes the MTPD from the first period in which any impact becomes unacceptable', function () use ($horizons): void {
    $matrix = new ImpactMatrix($horizons, ['money', 'law'], [
        'money|h4' => 1, 'money|day' => 3, 'money|week' => 5,
        'law|h4' => 2, 'law|day' => 4, 'law|week' => 4,
    ]);

    expect($matrix->mtpd(4))->toBe(1440)
        ->and($matrix->mtpd(5))->toBe(10080)
        ->and($matrix->mtpd(3))->toBe(1440)
        ->and($matrix->mtpd(2))->toBe(240);
});

it('gives no MTPD when nothing becomes unacceptable within the periods scored', function () use ($horizons): void {
    expect((new ImpactMatrix($horizons, ['money'], ['money|h4' => 1, 'money|day' => 2, 'money|week' => 3]))->mtpd(4))->toBeNull();
});

it('knows when every cell is scored and when impact goes down with time', function () use ($horizons): void {
    $partial = new ImpactMatrix($horizons, ['money'], ['money|h4' => 1, 'money|day' => 2]);
    $shrinking = new ImpactMatrix($horizons, ['money', 'law'], ['money|h4' => 3, 'money|day' => 2, 'money|week' => 4, 'law|h4' => 1, 'law|day' => 1, 'law|week' => 1]);

    expect($partial->isComplete())->toBeFalse()
        ->and($shrinking->isComplete())->toBeTrue()
        ->and($shrinking->decreasingCategories())->toBe(['money']);
});

it('stores durations in minutes and shows them in whole units', function (): void {
    expect(Duration::toMinutes('4', 'hours'))->toBe(240)
        ->and(Duration::toMinutes('3', 'days'))->toBe(4320)
        ->and(Duration::toMinutes('', 'hours'))->toBeNull()
        ->and(Duration::toMinutes('1.5', 'hours'))->toBeNull()
        ->and(Duration::split(4320))->toBe(['value' => '3', 'unit' => 'days'])
        ->and(Duration::split(90))->toBe(['value' => '90', 'unit' => 'minutes'])
        ->and(Duration::split(0))->toBe(['value' => '0', 'unit' => 'minutes']);
});

it('places a process in a tier by its RTO', function (): void {
    expect(Tier::fromRto(240))->toBe(Tier::One)
        ->and(Tier::fromRto(241))->toBe(Tier::Two)
        ->and(Tier::fromRto(1440))->toBe(Tier::Two)
        ->and(Tier::fromRto(4320))->toBe(Tier::Three)
        ->and(Tier::fromRto(4321))->toBe(Tier::Four);
});
