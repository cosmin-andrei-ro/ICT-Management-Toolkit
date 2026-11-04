<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Cmdb\Domain\Models\CiLink;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Risk\Application\Notifications\RiskUpdate;
use Modules\Risk\Application\Reminders\SendReviewReminders;
use Modules\Risk\Domain\Enums\AcceptanceStatus;
use Modules\Risk\Domain\Models\Acceptance;
use Modules\Risk\Domain\Models\Risk;
use Modules\Risk\Domain\Models\RiskAction;
use Modules\Risk\Domain\Models\ScoreRecord;
use Modules\Risk\Http\Livewire\ShowRisk;
use Modules\Risk\Tests\Support\RiskScenario;
use Modules\Ticketing\Domain\Enums\TicketType;
use Modules\Ticketing\Domain\Models\Ticket;

beforeEach(function (): void {
    Notification::fake();
    $this->risks = RiskScenario::create();
    $this->risk = Risk::factory()->create(['title' => 'Ransomware on payroll', 'owner_id' => $this->risks->owner->id, 'inherent_likelihood' => 4, 'inherent_impact' => 5, 'next_review_on' => today()->addMonths(6)]);
});

/**
 * The owner treats the risk down to the given residual scores.
 */
function treat(Risk $risk, int $likelihood, int $impact): void
{
    Livewire::test(ShowRisk::class, ['risk' => $risk])
        ->set('treatment', 'mitigate')->set('treatmentPlan', 'Offline backups and MFA')
        ->set('residualLikelihood', (string) $likelihood)->set('residualImpact', (string) $impact)
        ->call('saveTreatment')->assertHasNoErrors();
}

it('records the treatment, the residual risk and the history of scores', function (): void {
    $this->actingAs($this->risks->owner);
    treat($this->risk, 2, 4);

    $this->risk->refresh();
    expect($this->risk->treatment?->value)->toBe('mitigate')->and($this->risk->residualScore())->toBe(8)
        ->and(ScoreRecord::query()->where('risk_id', $this->risk->id)->sole()->residual_impact)->toBe(4);

    Livewire::test(ShowRisk::class, ['risk' => $this->risk])->set('residualLikelihood', '3')->set('residualImpact', '')->call('saveTreatment')->assertHasErrors('residualImpact');
    Livewire::test(ShowRisk::class, ['risk' => $this->risk])->assertSee('Medium · 8')->assertSee('Critical · 20');
});

it('tracks treatment actions, overdue ones and their problem in Ticketing', function (): void {
    (new CiLink)->forceFill(['organization_id' => $this->risk->organization_id, 'configuration_item_id' => $this->risks->payrollDb->id, 'linkable_type' => 'x', 'linkable_id' => $this->risk->id]);
    $this->actingAs($this->risks->owner);
    Livewire::test(ShowRisk::class, ['risk' => $this->risk])->call('addAsset', 'configuration_item', $this->risks->payrollDb->id)
        ->set('actionTitle', 'Enable MFA for all VPN users')->set('actionOwnerId', $this->risks->lead->id)->set('actionDueOn', today()->subDay()->toDateString())->call('addAction')->assertHasNoErrors()
        ->set('actionTitle', 'Test a restore')->call('addAction');

    $mfa = RiskAction::query()->where('title', 'Enable MFA for all VPN users')->sole();
    expect($mfa->isOverdue())->toBeTrue();

    Livewire::test(ShowRisk::class, ['risk' => $this->risk])
        ->call('raiseActionTicket', $mfa->id)->assertHasNoErrors()
        ->call('raiseActionTicket', $mfa->id)->assertHasErrors('actions')
        ->call('changeActionStatus', $mfa->id, 'done');

    $problem = Ticket::query()->where('type', TicketType::Problem)->sole();
    expect($problem->title)->toContain('R-0001')->toContain('Enable MFA')->and($problem->impact->value)->toBe('high')
        ->and(CiLink::query()->where('linkable_type', 'ticketing.ticket')->where('linkable_id', $problem->id)->pluck('configuration_item_id')->all())->toBe([$this->risks->payrollDb->id])
        ->and($mfa->fresh()?->completed_at)->not->toBeNull()->and($mfa->fresh()?->isOverdue())->toBeFalse();
});

it('lets the owner accept a residual risk below the threshold, and voids it when the residual risk changes', function (): void {
    $this->actingAs($this->risks->owner);
    treat($this->risk, 2, 3);
    Livewire::test(ShowRisk::class, ['risk' => $this->risk])
        ->call('requestAcceptance')->assertHasErrors('justification')
        ->set('justification', 'Remaining exposure is limited')->call('requestAcceptance')->assertHasNoErrors();

    $this->risk->refresh();
    expect($this->risk->isAccepted())->toBeTrue()->and($this->risk->accepted_until?->toDateString())->toBe(today()->addMonths(6)->toDateString())
        ->and(Acceptance::query()->sole()->decided_by)->toBe($this->risks->owner->id);

    treat($this->risk, 3, 3);
    expect($this->risk->fresh()?->isAccepted())->toBeFalse()->and(Acceptance::query()->sole()->status)->toBe(AcceptanceStatus::Lapsed);
});

it('needs a risk manager other than the owner and the requester from the threshold', function (): void {
    $otherManager = $this->risks->member(DefaultRole::RiskManager);
    // The first risk manager owns this risk: they may ask, never approve.
    $this->risk->forceFill(['owner_id' => $this->risks->manager->id])->save();

    $this->actingAs($this->risks->manager);
    treat($this->risk, 3, 4);
    Livewire::test(ShowRisk::class, ['risk' => $this->risk])->set('justification', 'Compensating monitoring is in place')->call('requestAcceptance')->assertHasNoErrors()
        ->assertSee('Acceptance waiting for approval')->assertDontSee('Approve</button>', false)
        ->call('approveAcceptance')->assertHasErrors('decisionComment');

    Notification::assertSentTo($otherManager, RiskUpdate::class, fn (RiskUpdate $mail): bool => $mail->kind === 'acceptance_requested');
    Notification::assertNotSentTo($this->risks->manager, RiskUpdate::class);
    expect($this->risk->fresh()?->isAccepted())->toBeFalse();

    $this->actingAs($otherManager);
    Livewire::test(ShowRisk::class, ['risk' => $this->risk])
        ->call('rejectAcceptance')->assertHasErrors('decisionComment')
        ->call('approveAcceptance')->assertHasNoErrors();

    expect($this->risk->fresh()?->isAccepted())->toBeTrue()->and(Acceptance::query()->sole()->decided_by)->toBe($otherManager->id);
    Notification::assertSentTo($this->risks->manager, RiskUpdate::class, fn (RiskUpdate $mail): bool => $mail->kind === 'acceptance_approved');

    // Owners who are not risk managers cannot decide either.
    $this->actingAs($this->risks->lead);
    Livewire::test(ShowRisk::class, ['risk' => $this->risk])->call('approveAcceptance')->assertNotFound();
});

it('reminds owners of reassessments once, and a saved assessment sets the next one', function (): void {
    $this->risk->forceFill(['next_review_on' => today()->addDays(10)])->save();

    expect(app(SendReviewReminders::class)->handle())->toBe(1);
    Notification::assertSentTo($this->risks->owner, RiskUpdate::class, fn (RiskUpdate $mail): bool => $mail->kind === 'review_due');
    expect(app(SendReviewReminders::class)->handle())->toBe(0);

    $this->actingAs($this->risks->owner);
    Livewire::test(ShowRisk::class, ['risk' => $this->risk])->call('assess')->assertHasNoErrors();
    expect($this->risk->fresh()?->next_review_on?->toDateString())->toBe(today()->addYear()->toDateString())
        ->and($this->risk->fresh()?->assessed_at)->not->toBeNull();
});
