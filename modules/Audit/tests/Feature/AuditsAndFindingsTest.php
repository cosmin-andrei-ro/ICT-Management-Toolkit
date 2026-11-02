<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Audit\Application\Actions\AddFramework;
use Modules\Audit\Application\Actions\RecordControlTest;
use Modules\Audit\Application\Notifications\FindingUpdate;
use Modules\Audit\Domain\Enums\TestResult;
use Modules\Audit\Domain\Models\Audit;
use Modules\Audit\Domain\Models\Control;
use Modules\Audit\Domain\Models\Finding;
use Modules\Audit\Domain\Models\FindingAction;
use Modules\Audit\Domain\Models\Framework;
use Modules\Audit\Domain\Models\Requirement;
use Modules\Audit\Http\Livewire\AuditForm;
use Modules\Audit\Http\Livewire\FindingForm;
use Modules\Audit\Http\Livewire\FindingIndex;
use Modules\Audit\Http\Livewire\MyControls;
use Modules\Audit\Http\Livewire\ShowAudit;
use Modules\Audit\Http\Livewire\ShowFinding;
use Modules\Audit\Tests\Support\ComplianceScenario;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;

beforeEach(function (): void {
    Notification::fake();
    $this->compliance = ComplianceScenario::create();
    $this->control = Control::factory()->create(['name' => 'Leavers removed the same day', 'owner_id' => $this->compliance->owner->id]);
});

/**
 * @param  array<string, string>  $extra
 */
function raiseFinding(ComplianceScenario $compliance, Audit $audit, string $severity, array $extra = []): Finding
{
    test()->actingAs($compliance->manager);
    $form = Livewire::withQueryParams(['audit' => $audit->id])->test(FindingForm::class)
        ->set('title', 'Leavers keep VPN access')
        ->set('description', '3 of 20 leavers kept access for more than 5 days')
        ->set('severity', $severity)
        ->set('ownerId', $compliance->owner->id)
        ->set('dueOn', today()->addMonth()->toDateString());

    foreach ($extra as $property => $value) {
        $form->set($property, $value);
    }

    $form->call('save')->assertHasNoErrors();

    return Finding::query()->latest('number')->firstOrFail();
}

it('plans, runs and completes audits; auditors only read them', function (): void {
    app(AddFramework::class)->fromCatalog('iso27001');
    $iso = Framework::query()->sole();

    $this->actingAs($this->compliance->manager);
    Livewire::test(AuditForm::class)
        ->set('title', 'Certification audit 2027')->set('kind', 'external')
        ->call('save')->assertHasErrors('auditBody')
        ->set('auditBody', 'Certification body')
        ->set('endsOn', today()->subDay()->toDateString())->call('save')->assertHasErrors('endsOn')
        ->set('endsOn', today()->addDays(2)->toDateString())
        ->set('frameworkIds', [$iso->id])
        ->set('leadAuditorId', $this->compliance->auditor->id)
        ->call('save')->assertHasNoErrors();

    $audit = Audit::query()->sole();
    expect($audit->code())->toBe('AUD-001')->and($audit->frameworks()->pluck('name')->all())->toBe([$iso->name]);

    Livewire::test(ShowAudit::class, ['audit' => $audit])
        ->call('changeStatus', 'completed')->assertHasErrors('conclusion')
        ->call('changeStatus', 'in_progress')->assertHasNoErrors()->assertSee('In progress')
        ->call('changeStatus', 'completed')->assertHasErrors('conclusion')
        ->set('conclusion', 'Effective, two minor nonconformities')
        ->call('changeStatus', 'completed')->assertHasNoErrors()->assertSee('Effective, two minor nonconformities');
    expect($audit->fresh()?->completed_at)->not->toBeNull()
        ->and(AuditLog::query()->where('auditable_id', $audit->id)->count())->toBeGreaterThan(2);

    $this->actingAs($this->compliance->auditor);
    $this->get(route('audit.audits.index'))->assertOk()->assertSee('AUD-001')->assertDontSee('New audit');
    $this->get(route('audit.audits.show', $audit))->assertOk();
    Livewire::test(ShowAudit::class, ['audit' => $audit])->call('changeStatus', 'in_progress')->assertForbidden();
    $this->get(route('audit.audits.create'))->assertForbidden();

    $this->actingAs($this->compliance->employee);
    $this->get(route('audit.audits.index'))->assertForbidden();
    $this->get(route('audit.audits.show', $audit))->assertForbidden();

    app(OrganizationContext::class)->set(Organization::factory()->create());
    $foreign = Audit::factory()->create();
    app(OrganizationContext::class)->set($this->compliance->organization);
    $this->actingAs($this->compliance->manager);
    $this->get(route('audit.audits.show', $foreign))->assertNotFound();
});

it('takes a nonconformity from finding to verified closure by someone other than its owner', function (): void {
    $audit = Audit::factory()->create();
    app(AddFramework::class)->fromCatalog('iso27001');
    $requirement = Requirement::query()->where('reference', '5.18')->sole();

    $this->actingAs($this->compliance->manager);
    $finding = raiseFinding($this->compliance, $audit, 'minor_nonconformity', ['controlId' => $this->control->id, 'requirementId' => $requirement->id]);
    expect($finding->code())->toBe('F-001')->and($finding->audit_id)->toBe($audit->id)->and($finding->requirement_id)->toBe($requirement->id);
    Notification::assertSentTo($this->compliance->owner, FindingUpdate::class, fn (FindingUpdate $update): bool => $update->kind === 'assigned');

    // The owner, a plain employee, finds it among their tasks and works it.
    $this->actingAs($this->compliance->owner);
    Livewire::test(MyControls::class)->assertSee('F-001');
    $page = Livewire::test(ShowFinding::class, ['finding' => $finding])
        ->call('submit')->assertHasErrors('rootCause')
        ->set('rootCause', 'HR tells IT by e-mail; messages are missed')
        ->set('actionTitle', 'Deactivate accounts from the HR system')
        ->set('actionOwnerId', $this->compliance->employee->id)
        ->call('addAction')->assertHasNoErrors()
        ->call('submit')->assertHasErrors('rootCause');
    $action = FindingAction::query()->sole();

    // The action's owner reports progress, nothing else.
    $this->actingAs($this->compliance->employee);
    Livewire::test(ShowFinding::class, ['finding' => $finding])
        ->call('setActionStatus', $action->id, 'done')
        ->call('submit')->assertForbidden();
    expect($action->fresh()?->completed_at)->not->toBeNull();

    $this->actingAs($this->compliance->owner);
    $page->call('submit')->assertHasNoErrors()->assertSee('Waiting for verification');
    expect($finding->fresh()?->submitted_by)->toBe($this->compliance->owner->id);
    Notification::assertSentTo($this->compliance->manager, FindingUpdate::class, fn (FindingUpdate $update): bool => $update->kind === 'submitted');
    Livewire::test(ShowFinding::class, ['finding' => $finding])->call('verify', true)->assertForbidden();

    // A second compliance manager reopens it with a reason, then closes it once fixed.
    $verifier = $this->compliance->member(DefaultRole::ComplianceManager);
    $this->actingAs($verifier);
    Livewire::test(ShowFinding::class, ['finding' => $finding])
        ->call('verify', false)->assertHasErrors('verificationNotes')
        ->set('verificationNotes', 'Two leavers in May still active')
        ->call('verify', false)->assertHasNoErrors();
    expect($finding->fresh()?->status->value)->toBe('open');
    Notification::assertSentTo($this->compliance->owner, FindingUpdate::class, fn (FindingUpdate $update): bool => $update->kind === 'reopened');

    $this->actingAs($this->compliance->owner);
    Livewire::test(ShowFinding::class, ['finding' => $finding->fresh()])->assertSee('Two leavers in May still active')->call('submit')->assertHasNoErrors();
    $this->actingAs($verifier);
    Livewire::test(ShowFinding::class, ['finding' => $finding])->set('verificationNotes', 'Sample of 10 leavers: all the same day')->call('verify', true)->assertHasNoErrors()->assertSee('Verified and closed by');
    expect($finding->fresh()?->verified_by)->toBe($verifier->id)
        ->and(AuditLog::query()->where('auditable_id', $finding->id)->count())->toBeGreaterThan(4);

    $this->actingAs($this->compliance->owner);
    Livewire::test(ShowFinding::class, ['finding' => $finding])->set('rootCause', 'x')->call('saveRootCause')->assertForbidden();
});

it('closes observations without corrective actions, and lists open and overdue findings', function (): void {
    $audit = Audit::factory()->create();
    $observation = raiseFinding($this->compliance, $audit, 'observation');
    $late = Finding::factory()->create(['audit_id' => $audit->id, 'title' => 'Late major', 'severity' => 'major_nonconformity', 'due_on' => today()->subDay(), 'owner_id' => $this->compliance->owner->id]);

    $this->actingAs($this->compliance->auditor);
    Livewire::test(FindingIndex::class)->assertSee('Late major')->assertSee($observation->title)
        ->set('overdue', true)->assertSee('Late major')->assertDontSee($observation->title);
    Livewire::test(FindingForm::class, ['finding' => $late])->assertForbidden();

    // A manager submits on the owner's behalf: neither may verify it.
    $this->actingAs($this->compliance->manager);
    Livewire::test(ShowFinding::class, ['finding' => $observation])->call('submit')->assertHasNoErrors()->call('verify', true)->assertForbidden();
});

it('raises a finding from a control test that was not effective', function (): void {
    $test = app(RecordControlTest::class)->handle($this->control, CarbonImmutable::today(), TestResult::Ineffective, 'Sample of 20 leavers', '5 kept access', null, $this->compliance->owner);

    $this->actingAs($this->compliance->manager);
    Livewire::withQueryParams(['test' => $test->id])->test(FindingForm::class)
        ->assertSet('controlId', $this->control->id)
        ->assertSet('ownerId', $this->compliance->owner->id)
        ->assertSet('severity', 'major_nonconformity')
        ->call('save')->assertHasNoErrors();

    $finding = Finding::query()->sole();
    expect($finding->test_id)->toBe($test->id)->and($finding->audit_id)->toBeNull()
        ->and($finding->title)->toBe('Control not effective: C-001 Leavers removed the same day');
    $this->get(route('audit.controls.show', $this->control))->assertSee('Raise a finding');
});
