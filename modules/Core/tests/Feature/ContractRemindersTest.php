<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Application\Contracts\SendContractReminders;
use Modules\Core\Application\Notifications\ContractDeadlineApproaching;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Contract;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\Supplier;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Tests\Support\ContractScenario;

beforeEach(function (): void {
    Notification::fake();
    config(['core.contracts.reminder_days' => [90, 30]]);
    $this->scenario = ContractScenario::create();
    $this->today = CarbonImmutable::parse('2026-06-01');
    $this->owner = $this->scenario->member(DefaultRole::Requester);
});

function contractEndingIn(ContractScenario $scenario, int $days, array $attributes = []): Contract
{
    return Contract::factory()->create([
        'supplier_id' => $scenario->supplier->id,
        'start_date' => '2025-01-01',
        'end_date' => CarbonImmutable::parse('2026-06-01')->addDays($days)->toDateString(),
        ...$attributes,
    ]);
}

it('emails the owner and the contract managers once per reminder, counting down to the deadline', function (): void {
    // Deadline: end date minus 30 days of notice = 85 days from today: inside the 90-day reminder.
    $contract = contractEndingIn($this->scenario, 115, ['notice_days' => 30, 'owner_id' => $this->owner->id]);
    $remind = app(SendContractReminders::class);

    expect($remind->handle($this->today))->toBe(1);
    Notification::assertSentTo([$this->owner, $this->scenario->manager], ContractDeadlineApproaching::class,
        fn (ContractDeadlineApproaching $mail): bool => $mail->deadline === '2026-08-25' && $mail->supplier === 'Acme Support');
    Notification::assertNotSentTo([$this->scenario->agent, $this->scenario->requester], ContractDeadlineApproaching::class);

    expect($remind->handle($this->today->addDay()))->toBe(0);
    Notification::assertSentToTimes($this->scenario->manager, ContractDeadlineApproaching::class, 1);

    // 30 days before the deadline: the second reminder.
    expect($remind->handle(CarbonImmutable::parse('2026-07-26')))->toBe(1);
    Notification::assertSentToTimes($this->scenario->manager, ContractDeadlineApproaching::class, 2);
    expect($contract->fresh())->not->toBeNull();
});

it('sends one email when several reminders are already due', function (): void {
    contractEndingIn($this->scenario, 10);

    expect(app(SendContractReminders::class)->handle($this->today))->toBe(1)
        ->and(app(SendContractReminders::class)->handle($this->today))->toBe(0);
    Notification::assertSentToTimes($this->scenario->manager, ContractDeadlineApproaching::class, 1);
});

it('reminds nobody about open-ended, terminated, ended or far-away contracts', function (): void {
    contractEndingIn($this->scenario, 0, ['end_date' => null]);
    contractEndingIn($this->scenario, 20, ['terminated_on' => '2026-05-01']);
    contractEndingIn($this->scenario, -1);
    contractEndingIn($this->scenario, 200);

    expect(app(SendContractReminders::class)->handle($this->today))->toBe(0);
    Notification::assertNothingSent();
});

it('reminds again when the end date moves', function (): void {
    $contract = contractEndingIn($this->scenario, 20);
    app(SendContractReminders::class)->handle($this->today);

    $contract->update(['end_date' => '2026-07-01']);
    expect(app(SendContractReminders::class)->handle($this->today))->toBe(1);
});

it('renews contracts that renew by themselves once their end passed', function (): void {
    $contract = contractEndingIn($this->scenario, -40, ['auto_renews' => true, 'renewal_months' => 12]);
    $fixed = contractEndingIn($this->scenario, -40);

    app(SendContractReminders::class)->handle($this->today);

    expect($contract->fresh()->end_date->toDateString())->toBe('2027-04-22')
        ->and($fixed->fresh()->end_date->toDateString())->toBe('2026-04-22');
});

it('emails the managers of the contract\'s own organization only', function (): void {
    $other = Organization::factory()->create();
    $theirManager = app(OrganizationContext::class)->runAs($other, function () use ($other) {
        $user = User::factory()->memberOf($other)->withTwoFactor()->create();
        $user->assignRole(DefaultRole::AssetManager->value);

        return $user;
    });
    // The manager of this organization is also a member of the other one, without a role there.
    $this->scenario->manager->organizations()->attach($other);

    app(OrganizationContext::class)->runAs($other, fn () => Contract::factory()->create([
        'supplier_id' => Supplier::factory()->create()->id,
        'start_date' => '2025-01-01',
        'end_date' => '2026-06-20',
    ]));
    app(OrganizationContext::class)->clear();

    app(SendContractReminders::class)->handle($this->today);

    Notification::assertSentTo($theirManager, ContractDeadlineApproaching::class);
    Notification::assertNotSentTo($this->scenario->manager, ContractDeadlineApproaching::class);
});

it('runs from the scheduler command', function (): void {
    contractEndingIn($this->scenario, 10);
    $this->travelTo($this->today);

    $this->artisan('core:contracts:remind')->expectsOutputToContain('1 contract reminder(s) sent.')->assertSuccessful();
});

it('says what to do and by when, without the value of the contract', function (): void {
    $mail = new ContractDeadlineApproaching('Server maintenance', 'Acme Support', 'C-42', '2026-08-25', '2026-09-24', true, 'https://toolkit.example/contracts/1');
    $rendered = (string) $mail->toMail($this->owner)->render();

    expect($rendered)->toContain('Server maintenance')->toContain('2026-08-25')->toContain('renews automatically on 2026-09-24')->toContain('C-42');
});
