<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Authorization\Role;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Pam\Application\Actions\ChangeSecret;
use Modules\Pam\Application\Metrics\PamMetrics;
use Modules\Pam\Application\Notifications\AccessUpdate;
use Modules\Pam\Domain\Enums\RequestStatus;
use Modules\Pam\Domain\Models\AccessRequest;
use Modules\Pam\Domain\Models\AccountGrant;
use Modules\Pam\Domain\Models\PrivilegedAccount;
use Modules\Pam\Http\Livewire\AccountForm;
use Modules\Pam\Http\Livewire\RequestInbox;
use Modules\Pam\Http\Livewire\ShowAccount;
use PragmaRX\Google2FA\Google2FA;

const APPROVED_SECRET = 'needs-approval-secret';

function approvalMember(object $test, DefaultRole $role): User
{
    $user = User::factory()->create();
    $user->organizations()->attach($test->organization);
    $user->assignRole(Role::query()->whereNull('organization_id')->where('name', $role->value)->sole());

    return $user;
}

function codeFor(User $user): string
{
    $secret = (new Google2FA)->generateSecretKey();
    $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()->subWeek(), 'two_factor_last_used_step' => null])->save();

    return (new Google2FA)->getCurrentOtp($secret);
}

beforeEach(function (): void {
    Notification::fake();
    app(SyncPermissions::class)->handle();
    $this->organization = Organization::factory()->create();
    app(OrganizationContext::class)->set($this->organization);
    $this->manager = approvalMember($this, DefaultRole::SecurityOfficer);
    $this->approver = approvalMember($this, DefaultRole::Agent);
    $this->agent = approvalMember($this, DefaultRole::Agent);
    $this->account = PrivilegedAccount::factory()->create(['name' => 'Core switch', 'requires_approval' => true, 'approver_id' => $this->approver->id, 'access_minutes' => 30]);
    app(ChangeSecret::class)->handle($this->account, APPROVED_SECRET, $this->manager);
    $this->account->refresh();
    AccountGrant::query()->create(['account_id' => $this->account->id, 'user_id' => $this->agent->id]);
});

function askFor(object $test): AccessRequest
{
    Livewire::actingAs($test->agent)->test(ShowAccount::class, ['account' => $test->account])
        ->set('reason', 'Replacing a line card')->call('askAccess')->assertHasNoErrors();

    return AccessRequest::query()->where('user_id', $test->agent->id)->latest()->firstOrFail();
}

it('shows nothing with a grant alone', function (): void {
    Livewire::actingAs($this->agent)->test(ShowAccount::class, ['account' => $this->account])
        ->assertSee(__('pam::accounts.requests.ask'))
        ->set('code', codeFor($this->agent))->call('reveal')->assertHasErrors('code')->assertDontSee(APPROVED_SECRET);
});

it('asks once, needs a reason, and tells the approver', function (): void {
    Livewire::actingAs($this->agent)->test(ShowAccount::class, ['account' => $this->account])->call('askAccess')->assertHasErrors('reason');
    askFor($this);
    askFor($this);

    expect(AccessRequest::query()->count())->toBe(1);
    Notification::assertSentTo($this->approver, AccessUpdate::class, fn (AccessUpdate $update): bool => $update->kind === 'request_waiting');
});

it('shows the credential after approval, until it expires, recording the request', function (): void {
    $request = askFor($this);

    Livewire::actingAs($this->approver)->test(RequestInbox::class)->assertSee('Replacing a line card')
        ->set('code', codeFor($this->approver))->call('approve', $request->id)->assertHasNoErrors();

    $request->refresh();
    expect($request->status)->toBe(RequestStatus::Approved)->and($request->decided_by)->toBe($this->approver->id)
        ->and(now()->addMinutes(30)->diffInSeconds($request->expires_at, true))->toBeLessThan(10);
    Notification::assertSentTo($this->agent, AccessUpdate::class, fn (AccessUpdate $update): bool => $update->kind === 'request_approved');

    Livewire::actingAs($this->agent)->test(ShowAccount::class, ['account' => $this->account])
        ->set('code', codeFor($this->agent))->call('reveal')->assertHasNoErrors()->assertSee(APPROVED_SECRET);
    $entry = AuditLog::query()->where('event', AuditEvent::SecretRevealed->value)->sole();
    expect($entry->new_values)->toMatchArray(['reason' => 'Replacing a line card', 'request' => $request->id]);

    $this->travel(31)->minutes();
    Livewire::actingAs($this->agent)->test(ShowAccount::class, ['account' => $this->account])
        ->assertSee(__('pam::accounts.requests.ask'))
        ->set('code', codeFor($this->agent))->call('reveal')->assertHasErrors('code')->assertDontSee(APPROVED_SECRET);
});

it('ends access at once when the grant is removed, even with an approval', function (): void {
    $request = askFor($this);
    Livewire::actingAs($this->approver)->test(RequestInbox::class)->set('code', codeFor($this->approver))->call('approve', $request->id);
    AccountGrant::query()->where('user_id', $this->agent->id)->delete();

    Livewire::actingAs($this->agent)->test(ShowAccount::class, ['account' => $this->account])->assertForbidden();
});

it('needs a code to decide, and a comment to reject', function (): void {
    $request = askFor($this);
    $inbox = Livewire::actingAs($this->approver)->test(RequestInbox::class);

    $inbox->call('approve', $request->id)->assertHasErrors('code');
    $inbox->set('code', '000000')->call('approve', $request->id)->assertHasErrors('code');
    $inbox->set('code', codeFor($this->approver))->call('reject', $request->id)->assertHasErrors('comment');
    expect($request->fresh()->status)->toBe(RequestStatus::Pending);

    $inbox->set('code', codeFor($this->approver))->set('comment', 'Not in a window')->call('reject', $request->id)->assertHasNoErrors();
    expect($request->fresh()->status)->toBe(RequestStatus::Rejected);
    Livewire::actingAs($this->agent)->test(ShowAccount::class, ['account' => $this->account])->assertSee('Not in a window');
});

it('does not let anyone decide on their own request, even a manager', function (): void {
    AccountGrant::query()->create(['account_id' => $this->account->id, 'user_id' => $this->manager->id]);
    Livewire::actingAs($this->manager)->test(ShowAccount::class, ['account' => $this->account])->set('reason', 'Mine')->call('askAccess');
    $own = AccessRequest::query()->where('user_id', $this->manager->id)->sole();

    expect($this->manager->can('decide', $own))->toBeFalse();
    Livewire::actingAs($this->manager)->test(RequestInbox::class)->assertDontSee('Mine')
        ->set('code', codeFor($this->manager))->call('approve', $own->id)->assertForbidden();
    expect(fn () => DB::table('pam_access_requests')->where('id', $own->id)->update(['decided_by' => $this->manager->id]))->toThrow(Illuminate\Database\QueryException::class);
});

it('refuses people who may not decide', function (): void {
    $request = askFor($this);
    $other = approvalMember($this, DefaultRole::Agent);

    Livewire::actingAs($other)->test(RequestInbox::class)->assertDontSee('Replacing a line card')
        ->set('code', codeFor($other))->call('approve', $request->id)->assertForbidden();
});

it('takes one decision only', function (): void {
    $request = askFor($this);
    $inbox = Livewire::actingAs($this->approver)->test(RequestInbox::class);
    Livewire::actingAs($this->manager)->test(RequestInbox::class)->set('code', codeFor($this->manager))->call('approve', $request->id);

    $inbox->set('code', codeFor($this->approver))->set('comment', 'No')->call('reject', $request->id)->assertForbidden();
    expect($request->fresh()->status)->toBe(RequestStatus::Approved);
});

it('needs a code to weaken who decides, but not for other edits', function (): void {
    $this->actingAs($this->manager);
    $form = Livewire::test(AccountForm::class, ['account' => $this->account]);

    $form->set('requiresApproval', false)->call('save')->assertHasErrors('code');
    expect($this->account->fresh()->requires_approval)->toBeTrue();

    Livewire::test(AccountForm::class, ['account' => $this->account])->set('description', 'New text')->call('save')->assertHasNoErrors();
    expect($this->account->fresh()->description)->toBe('New text');

    Livewire::test(AccountForm::class, ['account' => $this->account])->set('requiresApproval', false)->set('code', codeFor($this->manager))->call('save')->assertHasNoErrors();
    expect($this->account->fresh()->requires_approval)->toBeFalse()
        ->and(AuditLog::query()->where('auditable_id', $this->account->id)->where('event', AuditEvent::Updated->value)->get()->pluck('new_values')->contains(fn (mixed $values): bool => is_array($values) && ($values['requires_approval'] ?? null) === false))->toBeTrue();
});

it('validates the access rules', function (): void {
    $this->actingAs($this->manager);
    Livewire::test(AccountForm::class, ['account' => $this->account])->set('accessMinutes', 1)->call('save')->assertHasErrors('accessMinutes');
    Livewire::test(AccountForm::class, ['account' => $this->account])->set('rotationDays', '0')->call('save')->assertHasErrors('rotationDays');
    Livewire::test(AccountForm::class, ['account' => $this->account])->set('approverId', User::factory()->create()->id)->call('save')->assertHasErrors('approverId');
});

it('reminds once to replace a credential, until it is replaced', function (): void {
    $owner = approvalMember($this, DefaultRole::Agent);
    $this->account->update(['owner_id' => $owner->id, 'rotation_days' => 30]);
    $this->travel(20)->days();

    Artisan::call('pam:reminders');
    Artisan::call('pam:reminders');
    expect(Notification::sent($owner, AccessUpdate::class)->filter(fn (AccessUpdate $update): bool => $update->kind === 'rotation_due'))->toHaveCount(1);

    app(ChangeSecret::class)->handle($this->account->fresh(), 'new-value', $this->manager);
    $fresh = $this->account->fresh();
    expect($fresh->rotation_reminded_at)->toBeNull()->and($fresh->rotationDueOn()?->isFuture())->toBeTrue();
});

it('reminds those who manage the vault when an account has no owner', function (): void {
    $this->account->update(['owner_id' => null, 'review_days' => 10]);

    Artisan::call('pam:reminders');

    Notification::assertSentTo($this->manager, AccessUpdate::class, fn (AccessUpdate $update): bool => $update->kind === 'review_due');
});

it('starts a new review period when someone confirms the list, with a code', function (): void {
    $this->account->update(['review_days' => 90]);
    $page = Livewire::actingAs($this->manager)->test(ShowAccount::class, ['account' => $this->account]);
    $page->call('confirmReview')->assertHasErrors('actionCode');
    expect($this->account->fresh()->reviewed_at)->toBeNull();

    $page->set('actionCode', codeFor($this->manager))->call('confirmReview')->assertHasNoErrors();
    $fresh = $this->account->fresh();
    expect($fresh->reviewed_by)->toBe($this->manager->id)->and($fresh->reviewDueOn()?->isFuture())->toBeTrue();
    expect(AuditLog::query()->where('auditable_id', $this->account->id)->get()->pluck('new_values')->contains(fn (mixed $values): bool => is_array($values) && ($values['access_reviewed'] ?? null) === 1))->toBeTrue();

    Livewire::actingAs($this->agent)->test(ShowAccount::class, ['account' => $this->account])->call('confirmReview')->assertForbidden();
});

it('counts overdue duties and waiting requests, for this organization only', function (): void {
    $this->account->update(['rotation_days' => 1, 'review_days' => 1]);
    askFor($this);
    $this->travel(3)->days();

    expect(PamMetrics::values())->toBe(['pam.rotation_overdue' => 1, 'pam.review_overdue' => 1, 'pam.requests_waiting' => 1]);

    app(OrganizationContext::class)->set(Organization::factory()->create());
    expect(PamMetrics::values())->toBe(['pam.rotation_overdue' => 0, 'pam.review_overdue' => 0, 'pam.requests_waiting' => 0]);
});

it('tells those who manage the vault when the only named person is the one asking', function (): void {
    $this->account->update(['approver_id' => null, 'owner_id' => $this->agent->id]);
    askFor($this);

    Notification::assertSentTo($this->manager, AccessUpdate::class, fn (AccessUpdate $update): bool => $update->kind === 'request_waiting');
    Notification::assertNotSentTo($this->agent, AccessUpdate::class);
});

it('does not bring back an approval when access is given back', function (): void {
    $request = askFor($this);
    Livewire::actingAs($this->approver)->test(RequestInbox::class)->set('code', codeFor($this->approver))->call('approve', $request->id);
    $grant = AccountGrant::query()->where('user_id', $this->agent->id)->sole();

    Livewire::actingAs($this->manager)->test(ShowAccount::class, ['account' => $this->account])->call('revoke', $grant->id);
    AccountGrant::query()->create(['account_id' => $this->account->id, 'user_id' => $this->agent->id]);

    Livewire::actingAs($this->agent)->test(ShowAccount::class, ['account' => $this->account])
        ->set('code', codeFor($this->agent))->call('reveal')->assertHasErrors('code')->assertDontSee(APPROVED_SECRET);
});

it('keeps the inbox working after an account with a waiting request is deleted', function (): void {
    askFor($this);
    Livewire::actingAs($this->manager)->test(ShowAccount::class, ['account' => $this->account])->set('actionCode', codeFor($this->manager))->call('delete');

    expect(AccessRequest::query()->count())->toBe(0);
    Livewire::actingAs($this->approver)->test(RequestInbox::class)->assertOk()->assertSee(__('pam::accounts.requests.none'));
});

it('does not take decisions or reviews from an authenticator app set up just now', function (): void {
    $request = askFor($this);
    $code = codeFor($this->approver);
    $this->approver->forceFill(['two_factor_confirmed_at' => now()->subHour()])->save();

    Livewire::actingAs($this->approver)->test(RequestInbox::class)->set('code', $code)->call('approve', $request->id)->assertHasErrors('code');
    expect($request->fresh()->status)->toBe(RequestStatus::Pending);

    $code = codeFor($this->manager);
    $this->manager->forceFill(['two_factor_confirmed_at' => now()->subHour()])->save();
    Livewire::actingAs($this->manager)->test(ShowAccount::class, ['account' => $this->account])->set('actionCode', $code)->call('confirmReview')->assertHasErrors('actionCode');
    expect($this->account->fresh()->reviewed_at)->toBeNull();
});

it('needs a code to change how long access lasts', function (): void {
    $this->actingAs($this->manager);
    Livewire::test(AccountForm::class, ['account' => $this->account])->set('accessMinutes', 1440)->call('save')->assertHasErrors('code');

    expect($this->account->fresh()->access_minutes)->toBe(30);
});

it('drops waiting requests when someone leaves', function (): void {
    askFor($this);
    app(Modules\Core\Application\Users\RemoveFromOrganization::class)->handle($this->agent);

    expect(AccessRequest::query()->count())->toBe(0);
});
