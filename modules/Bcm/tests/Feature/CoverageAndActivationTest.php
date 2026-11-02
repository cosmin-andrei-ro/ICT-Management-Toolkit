<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Bcm\Application\Coverage\ContinuityCoverage;
use Modules\Bcm\Application\Exports\BcmExport;
use Modules\Bcm\Application\Notifications\ContinuityDigest;
use Modules\Bcm\Application\Notifications\PlanActivated;
use Modules\Bcm\Application\Notifications\PlanDue;
use Modules\Bcm\Application\Reminders\SendContinuityDigest;
use Modules\Bcm\Application\Reminders\SendPlanReminders;
use Modules\Bcm\Domain\Models\Activation;
use Modules\Bcm\Domain\Models\Exercise;
use Modules\Bcm\Domain\Models\Plan;
use Modules\Bcm\Http\Livewire\ActivationForm;
use Modules\Bcm\Http\Livewire\ActivationPage;
use Modules\Bcm\Http\Livewire\CoverageReport;
use Modules\Bcm\Tests\Support\BcmScenario;
use Modules\Bia\Domain\Models\BusinessProcess;
use Modules\Core\Application\Exports\RequestExport;
use Modules\Core\Domain\Exports\ExportFormat;
use Modules\Core\Domain\Models\Export;

beforeEach(function (): void {
    Notification::fake();
    $this->bcm = BcmScenario::create();
});

it('shows which assessed processes have a plan in force that brings them back in time', function (): void {
    BusinessProcess::factory()->create(['name' => 'Invoicing', 'rto_minutes' => 60, 'tier' => 1, 'approved_at' => now()]);
    $plan = $this->bcm->approvedPayrollDrp();
    Plan::factory()->create(['name' => 'Head office loss', 'type' => 'bcp']);

    $figures = app(ContinuityCoverage::class)->figures(CarbonImmutable::today());
    expect($figures)->toMatchArray(['plans' => 2, 'not_in_force' => 1, 'uncovered' => 1, 'exercise_overdue' => 0, 'review_overdue' => 0]);

    $this->actingAs($this->bcm->agent);
    Livewire::test(CoverageReport::class)
        ->assertSee('Payroll DRP')->assertSee('Head office loss')->assertSee('Not in force')
        ->assertSee('Invoicing')->assertSee('No plan')
        ->set('gapsOnly', true)->assertSee('Invoicing')
        ->call('export', 'xlsx');
    expect(Export::query()->where('type', BcmExport::TYPE)->exists())->toBeTrue();

    // The payroll plan: 3 hours estimated against the 4-hour RTO; not exercised.
    $export = Export::factory()->create(['type' => BcmExport::TYPE, 'user_id' => $this->bcm->coordinator->id]);
    [$plans, $coverage] = app(BcmExport::class)->sheets($export);
    $row = collect(iterator_to_array($plans->rows, false))->map(fn (array $values): array => array_combine($plans->headings, $values))->firstWhere('Plan', 'Payroll DRP');
    expect($row)->toMatchArray(['Version in force' => 1, 'Estimated recovery (minutes)' => 180, 'Strictest RTO (minutes)' => 240, 'Within RTO' => true, 'Last exercised' => null])
        ->and(iterator_to_array($coverage->rows, false))->toContain(['Invoicing', 1, 60, '', null, false]);

    $this->actingAs($this->bcm->employee);
    Livewire::test(CoverageReport::class)->assertForbidden();
    expect(fn () => app(RequestExport::class)->handle($this->bcm->employee, BcmExport::TYPE, ExportFormat::Xlsx, []))->toThrow(AuthorizationException::class);
    expect($plan->exists)->toBeTrue();
});

it('reminds owners of reviews and exercises once per due date, unless already under way', function (): void {
    $plan = $this->bcm->approvedPayrollDrp();
    $plan->forceFill(['next_review_on' => today()->addDays(20), 'approved_at' => now()->subMonths(12)->addDays(10)])->save();

    expect(app(SendPlanReminders::class)->handle())->toBe(['reviews' => 1, 'exercises' => 1]);
    Notification::assertSentTo($this->bcm->owner, PlanDue::class, fn (PlanDue $due): bool => $due->kind === 'review');
    Notification::assertSentTo($this->bcm->owner, PlanDue::class, fn (PlanDue $due): bool => $due->kind === 'exercise');
    expect(app(SendPlanReminders::class)->handle())->toBe(['reviews' => 0, 'exercises' => 0]);

    // A new due date is announced again, unless an exercise is already planned.
    $plan->forceFill(['last_exercised_on' => today()->subMonths(12)->addDays(5)])->save();
    Exercise::factory()->create(['plan_id' => $plan->id]);
    expect(app(SendPlanReminders::class)->handle())->toBe(['reviews' => 0, 'exercises' => 0])
        ->and($plan->fresh()?->exercise_reminded_for?->toDateString())->toBe(today()->addDays(5)->toDateString());
});

it('emails coordinators what needs attention each week', function (): void {
    BusinessProcess::factory()->create(['name' => 'Invoicing', 'rto_minutes' => 60, 'approved_at' => now()]);

    expect(app(SendContinuityDigest::class)->handle())->toBe(1);
    Notification::assertSentTo($this->bcm->coordinator, ContinuityDigest::class, fn (ContinuityDigest $digest): bool => $digest->figures === ['uncovered' => 2]);
    Notification::assertNotSentTo($this->bcm->agent, ContinuityDigest::class);
});

it('records a real activation, tells the crisis team and closes it once the service is back', function (): void {
    $plan = $this->bcm->approvedPayrollDrp();

    $this->actingAs($this->bcm->agent);
    Livewire::test(ActivationForm::class, ['plan' => $plan])->assertForbidden();

    $this->actingAs($this->bcm->owner);
    Livewire::test(ActivationForm::class, ['plan' => $plan])
        ->set('activatedAt', now()->addHour()->setTimezone(config()->string('app.display_timezone'))->format('Y-m-d\TH:i'))
        ->set('reason', 'Payroll database corrupted')
        ->call('save')
        ->assertHasErrors('activatedAt');

    $start = now()->subHours(5)->startOfMinute();
    Livewire::test(ActivationForm::class, ['plan' => $plan])
        ->set('activatedAt', $start->copy()->setTimezone(config()->string('app.display_timezone'))->format('Y-m-d\TH:i'))
        ->set('scenario', 'it_outage')
        ->set('reason', 'Payroll database corrupted')
        ->call('save')
        ->assertHasNoErrors();

    $activation = Activation::query()->sole();
    expect($activation->rto_minutes)->toBe(240)->and($activation->activated_at->toIso8601String())->toBe($start->toIso8601String());
    Notification::assertSentTo($this->bcm->coordinator, PlanActivated::class);
    Notification::assertNotSentTo($this->bcm->owner, PlanActivated::class);

    $local = fn ($instant): string => $instant->copy()->setTimezone(config()->string('app.display_timezone'))->format('Y-m-d\TH:i');
    $page = Livewire::test(ActivationPage::class, ['activation' => $activation])
        ->call('close')->assertHasErrors('restoredAt')
        ->set('restoredAt', $local($start->copy()->subHour()))->call('save')->assertHasErrors('restoredAt')
        ->set('restoredAt', $local($start->copy()->addHours(5)))
        ->set('lessons', 'Keep a warm standby of the database')
        ->call('close')
        ->assertHasNoErrors()
        ->assertSee('The service was back 5 hours after the activation.')
        ->assertSee('Longer than the strictest RTO');

    expect($activation->fresh()?->closed_by)->toBe($this->bcm->owner->id);
    $page->call('save')->assertForbidden();
    expect($activation->auditLogs()->count())->toBeGreaterThan(1);
});
