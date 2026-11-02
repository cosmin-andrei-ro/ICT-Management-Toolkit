<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Itam\Application\Notifications\EndingSoon;
use Modules\Itam\Application\Warranty\SendWarrantyReminders;
use Modules\Itam\Tests\Support\ItamScenario;

beforeEach(function (): void {
    Notification::fake();
    config(['itam.assets.warranty_reminder_days' => 30]);
    $this->itam = ItamScenario::create();
    $this->today = CarbonImmutable::parse('2026-06-01');
});

it('emails asset managers once about warranties ending soon, and again for a new end date', function (): void {
    $soon = $this->itam->laptop(['asset_tag' => 'AST-SOON', 'warranty_ends_on' => '2026-06-20']);
    $this->itam->laptop(['asset_tag' => 'AST-LATER', 'warranty_ends_on' => '2026-09-01']);
    $this->itam->laptop(['asset_tag' => 'AST-OVER', 'warranty_ends_on' => '2026-05-01']);
    $this->itam->laptop(['asset_tag' => 'AST-GONE', 'warranty_ends_on' => '2026-06-10', 'status' => 'retired']);
    $lead = $this->itam->member(DefaultRole::TeamLead);
    $remind = app(SendWarrantyReminders::class);

    expect($remind->handle($this->today))->toBe(1);
    Notification::assertSentTo([$this->itam->manager, $lead], EndingSoon::class,
        fn (EndingSoon $mail): bool => count($mail->lines) === 1 && str_starts_with($mail->lines[0], 'AST-SOON'));
    Notification::assertNotSentTo([$this->itam->agent, $this->itam->employee], EndingSoon::class);

    expect($remind->handle($this->today->addDay()))->toBe(0);

    $soon->update(['warranty_ends_on' => '2026-06-25']);
    expect($remind->handle($this->today->addDay()))->toBe(1);
});

it('runs from the scheduler command', function (): void {
    $this->itam->laptop(['warranty_ends_on' => now()->addDays(5)->toDateString()]);

    $this->artisan('itam:warranties:remind')->expectsOutputToContain('1 warranty end(s) announced.')->assertSuccessful();
});
