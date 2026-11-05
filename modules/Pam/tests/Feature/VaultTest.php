<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
use Modules\Pam\Application\Notifications\AccessGranted;
use Modules\Pam\Application\PamVault;
use Modules\Pam\Domain\Exceptions\VaultNotConfigured;
use Modules\Pam\Domain\Models\AccountGrant;
use Modules\Pam\Domain\Models\PrivilegedAccount;
use Modules\Pam\Http\Livewire\AccessLog;
use Modules\Pam\Http\Livewire\AccountForm;
use Modules\Pam\Http\Livewire\AccountIndex;
use Modules\Pam\Http\Livewire\ShowAccount;
use PragmaRX\Google2FA\Google2FA;

const PAM_SECRET = 'very-secret-value-for-tests';

function pamMember(object $test, DefaultRole $role): User
{
    $user = User::factory()->create();
    $user->organizations()->attach($test->organization);
    $user->assignRole(Role::query()->whereNull('organization_id')->where('name', $role->value)->sole());

    return $user;
}

function withCode(User $user): string
{
    $secret = (new Google2FA)->generateSecretKey();
    // Each call a new secret and no used step, so tests can act several times. Set up long ago: see the settling test.
    $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()->subWeek(), 'two_factor_last_used_step' => null])->save();

    return (new Google2FA)->getCurrentOtp($secret);
}

beforeEach(function (): void {
    app(SyncPermissions::class)->handle();
    $this->organization = Organization::factory()->create();
    app(OrganizationContext::class)->set($this->organization);
    $this->manager = pamMember($this, DefaultRole::SecurityOfficer);
    $this->agent = pamMember($this, DefaultRole::Agent);
    $this->employee = pamMember($this, DefaultRole::Requester);
    $this->account = PrivilegedAccount::factory()->create(['name' => 'Database admin', 'username' => 'dba']);
    app(ChangeSecret::class)->handle($this->account, PAM_SECRET, $this->manager);
    $this->account->refresh();
});

it('stores the credential encrypted with its own key, and not with the application key', function (): void {
    $stored = DB::table('pam_accounts')->where('id', $this->account->id)->value('secret_ciphertext');

    expect($stored)->toBeString()->not->toContain(PAM_SECRET)
        ->and(app(PamVault::class)->decrypt($this->account->id, $stored))->toBe(PAM_SECRET)
        ->and(fn () => decrypt($stored, false))->toThrow(Illuminate\Contracts\Encryption\DecryptException::class);
});

it('keeps the credential out of serialization and the audit trail', function (): void {
    expect($this->account->toArray())->not->toHaveKey('secret_ciphertext');

    $logged = AuditLog::query()->where('auditable_id', $this->account->id)->get()->toJson();
    expect($logged)->not->toContain(PAM_SECRET)->not->toContain((string) $this->account->secret_ciphertext)->toContain('changed');
});

it('refuses to work without its own key', function (string $key): void {
    config()->set('pam.vault.key', $key === 'app' ? config('app.key') : $key);

    expect(app(PamVault::class)->isConfigured())->toBeFalse()
        ->and(fn () => app(PamVault::class)->encrypt('id', 'x'))->toThrow(VaultNotConfigured::class);
})->with(['missing' => '', 'invalid' => 'not-a-key', 'too short' => 'base64:c2hvcnQ=', 'same as the application key' => 'app']);

it('shows the credential to someone granted, with a fresh code and a reason, and records it', function (): void {
    AccountGrant::query()->create(['account_id' => $this->account->id, 'user_id' => $this->agent->id]);
    $code = withCode($this->agent);

    Livewire::actingAs($this->agent)->test(ShowAccount::class, ['account' => $this->account])
        ->assertDontSee(PAM_SECRET)
        ->set('reason', 'Night maintenance')->set('code', $code)->call('reveal')
        ->assertHasNoErrors()->assertSee(PAM_SECRET)->assertSet('code', '')
        ->call('hide')->assertDontSee(PAM_SECRET);

    $entry = AuditLog::query()->where('auditable_id', $this->account->id)->where('event', AuditEvent::SecretRevealed->value)->sole();
    expect($entry->user_id)->toBe($this->agent->id)->and($entry->new_values['reason'])->toBe('Night maintenance');
});

it('needs a valid code and a reason, and limits attempts', function (): void {
    AccountGrant::query()->create(['account_id' => $this->account->id, 'user_id' => $this->agent->id]);
    withCode($this->agent);
    $component = Livewire::actingAs($this->agent)->test(ShowAccount::class, ['account' => $this->account]);

    $component->set('reason', '')->set('code', '000000')->call('reveal')->assertHasErrors('reason')->assertDontSee(PAM_SECRET);

    foreach (range(1, 5) as $attempt) {
        $component->set('reason', 'x')->set('code', '000000')->call('reveal')->assertHasErrors('code')->assertDontSee(PAM_SECRET);
    }

    expect(AuditLog::query()->where('event', AuditEvent::MfaFailed->value)->where('user_id', $this->agent->id)->count())->toBeGreaterThan(0)
        ->and(AuditLog::query()->where('event', AuditEvent::SecretRevealed->value)->count())->toBe(0);
});

it('refuses someone without a second factor', function (): void {
    AccountGrant::query()->create(['account_id' => $this->account->id, 'user_id' => $this->agent->id]);

    Livewire::actingAs($this->agent)->test(ShowAccount::class, ['account' => $this->account])
        ->set('reason', 'x')->set('code', '123456')->call('reveal')->assertHasErrors('code')->assertDontSee(PAM_SECRET);
});

it('does not let a manager read a credential: managing is not reading', function (): void {
    $code = withCode($this->manager);

    Livewire::actingAs($this->manager)->test(ShowAccount::class, ['account' => $this->account])
        ->assertSee(__('pam::accounts.ui.not_granted'))
        ->set('reason', 'x')->set('code', $code)->call('reveal')->assertForbidden();
    expect(AuditLog::query()->where('event', AuditEvent::SecretRevealed->value)->count())->toBe(0);
});

it('does not let anyone grant themselves access', function (): void {
    $this->actingAs($this->manager);
    Livewire::test(ShowAccount::class, ['account' => $this->account])
        ->set('actionCode', withCode($this->manager))->set('grantUserId', $this->manager->id)->call('grant')->assertHasErrors('grantUserId');

    expect(AccountGrant::query()->count())->toBe(0)
        ->and($this->manager->can('grantTo', [$this->account, $this->manager]))->toBeFalse();
});

it('grants only people with the right to use the vault, and records grants and removals', function (): void {
    $this->actingAs($this->manager);
    $component = Livewire::test(ShowAccount::class, ['account' => $this->account]);
    $component->set('actionCode', withCode($this->manager))->set('grantUserId', $this->employee->id)->call('grant')->assertHasErrors('grantUserId');
    $component->set('actionCode', withCode($this->manager))->set('grantUserId', $this->agent->id)->call('grant')->assertHasNoErrors();

    $grant = AccountGrant::query()->sole();
    expect($grant->user_id)->toBe($this->agent->id)
        ->and(AuditLog::query()->where('auditable_id', $grant->id)->where('event', AuditEvent::Created->value)->exists())->toBeTrue();

    $component->call('revoke', $grant->id);
    expect(AccountGrant::query()->count())->toBe(0)
        ->and($this->agent->can('reveal', $this->account))->toBeFalse();
});

it('shows people only the accounts they own or were granted', function (): void {
    $mine = PrivilegedAccount::factory()->create(['name' => 'Granted one']);
    AccountGrant::query()->create(['account_id' => $mine->id, 'user_id' => $this->agent->id]);
    $owned = PrivilegedAccount::factory()->create(['name' => 'Owned one', 'owner_id' => $this->agent->id]);

    Livewire::actingAs($this->agent)->test(AccountIndex::class)
        ->assertSee('Granted one')->assertSee('Owned one')->assertDontSee('Database admin');
    Livewire::actingAs($this->agent)->test(ShowAccount::class, ['account' => $this->account])->assertForbidden();
    Livewire::actingAs($this->manager)->test(AccountIndex::class)->assertSee('Database admin');
});

it('refuses people without any right', function (): void {
    Livewire::actingAs($this->employee)->test(AccountIndex::class)->assertForbidden();
    Livewire::actingAs($this->employee)->test(ShowAccount::class, ['account' => $this->account])->assertForbidden();
    Livewire::actingAs($this->employee)->test(AccountForm::class)->assertForbidden();
    Livewire::actingAs($this->employee)->test(AccessLog::class)->assertForbidden();
});

it('lets those who keep the account replace the credential, and nobody else', function (): void {
    Livewire::actingAs($this->manager)->test(ShowAccount::class, ['account' => $this->account])
        ->set('actionCode', withCode($this->manager))->set('newSecret', 'a-new-credential')->call('changeSecret')->assertHasNoErrors()->assertSet('newSecret', '');
    expect(app(PamVault::class)->decrypt($this->account->id, (string) $this->account->fresh()->secret_ciphertext))->toBe('a-new-credential');

    AccountGrant::query()->create(['account_id' => $this->account->id, 'user_id' => $this->agent->id]);
    Livewire::actingAs($this->agent)->test(ShowAccount::class, ['account' => $this->account])
        ->set('newSecret', 'evil')->call('changeSecret')->assertForbidden();
});

it('destroys the credential when an account is deleted, and keeps the record for the trail', function (): void {
    Livewire::actingAs($this->manager)->test(ShowAccount::class, ['account' => $this->account])->set('actionCode', withCode($this->manager))->call('delete');

    $row = DB::table('pam_accounts')->where('id', $this->account->id)->first();
    expect($row->secret_ciphertext)->toBeNull()->and($row->deleted_at)->not->toBeNull();
});

it('creates and edits accounts, validating input', function (): void {
    $this->actingAs($this->manager);
    Livewire::test(AccountForm::class)->set('name', '')->call('save')->assertHasErrors(['name', 'username']);
    Livewire::test(AccountForm::class)->set('name', 'Firewall')->set('username', 'admin')->set('type', 'nonsense')->call('save')->assertHasErrors('type');
    Livewire::test(AccountForm::class)->set('name', 'Firewall')->set('username', 'admin')->set('type', 'administrator')->set('ownerId', $this->agent->id)->call('save')->assertHasNoErrors();

    $account = PrivilegedAccount::query()->where('name', 'Firewall')->sole();
    expect($account->owner_id)->toBe($this->agent->id)->and($this->agent->can('update', $account))->toBeTrue();
});

it('ignores links to records it cannot find', function (): void {
    $this->actingAs($this->manager);
    Livewire::test(AccountForm::class, ['account' => $this->account])->set('configurationItemId', '01a1a7cd-3a08-7258-9420-aa72cc000000')->call('save')->assertHasNoErrors();

    expect($this->account->fresh()->configuration_item_id)->toBeNull();
});

it('keeps organizations apart', function (): void {
    AccountGrant::query()->create(['account_id' => $this->account->id, 'user_id' => $this->agent->id]);
    $other = Organization::factory()->create();
    app(OrganizationContext::class)->set($other);

    expect(PrivilegedAccount::query()->count())->toBe(0)->and($this->agent->can('reveal', $this->account))->toBeFalse();
});

it('shows the access log to those who may read it, with reasons, never credentials', function (): void {
    AccountGrant::query()->create(['account_id' => $this->account->id, 'user_id' => $this->agent->id]);
    Livewire::actingAs($this->agent)->test(ShowAccount::class, ['account' => $this->account])
        ->set('reason', 'Night maintenance')->set('code', withCode($this->agent))->call('reveal');

    Livewire::actingAs(pamMember($this, DefaultRole::Auditor))->test(AccessLog::class)
        ->assertSee('Night maintenance')->assertSee('Database admin')->assertDontSee(PAM_SECRET);
    Livewire::actingAs($this->agent)->test(AccessLog::class)->assertForbidden();
});

it('moves credentials to a new key, and leaves what it cannot read', function (): void {
    $old = config('pam.vault.key');
    $new = PamVault::generateKey();
    config()->set('pam.vault.key', $new);
    config()->set('pam.vault.previous_keys', [$old]);

    expect(Artisan::call('pam:rekey'))->toBe(0);

    config()->set('pam.vault.previous_keys', []);
    expect(app(PamVault::class)->decrypt($this->account->id, (string) $this->account->fresh()->secret_ciphertext))->toBe(PAM_SECRET);

    config()->set('pam.vault.key', PamVault::generateKey());
    $before = DB::table('pam_accounts')->where('id', $this->account->id)->value('secret_ciphertext');
    expect(Artisan::call('pam:rekey'))->toBe(1)
        ->and(DB::table('pam_accounts')->where('id', $this->account->id)->value('secret_ciphertext'))->toBe($before);
});

it('says so when the key cannot read a credential, instead of failing', function (): void {
    AccountGrant::query()->create(['account_id' => $this->account->id, 'user_id' => $this->agent->id]);
    config()->set('pam.vault.key', PamVault::generateKey());

    Livewire::actingAs($this->agent)->test(ShowAccount::class, ['account' => $this->account])
        ->set('reason', 'x')->set('code', withCode($this->agent))->call('reveal')
        ->assertHasErrors('code')->assertOk();
    expect(AuditLog::query()->where('event', AuditEvent::SecretRevealed->value)->count())->toBe(0);
});

it('needs a fresh code to change a credential, give access or delete an account', function (string $action): void {
    $component = Livewire::actingAs($this->manager)->test(ShowAccount::class, ['account' => $this->account])
        ->set('newSecret', 'replaced')->set('grantUserId', $this->agent->id);

    $component->call($action)->assertHasErrors('actionCode');
    $component->set('actionCode', '000000')->call($action)->assertHasErrors('actionCode');

    expect(app(PamVault::class)->decrypt($this->account->id, (string) $this->account->fresh()->secret_ciphertext))->toBe(PAM_SECRET)
        ->and(AccountGrant::query()->count())->toBe(0)
        ->and(PrivilegedAccount::query()->whereKey($this->account->id)->exists())->toBeTrue();
})->with(['changeSecret', 'grant', 'delete']);

it('makes a newly set up authenticator app wait, against a stolen session', function (): void {
    AccountGrant::query()->create(['account_id' => $this->account->id, 'user_id' => $this->agent->id]);
    $code = withCode($this->agent);
    $this->agent->forceFill(['two_factor_confirmed_at' => now()->subHours(23)])->save();

    Livewire::actingAs($this->agent)->test(ShowAccount::class, ['account' => $this->account])
        ->set('reason', 'x')->set('code', $code)->call('reveal')->assertHasErrors('code')->assertDontSee(PAM_SECRET);

    $this->agent->forceFill(['two_factor_confirmed_at' => now()->subHours(25)])->save();
    Livewire::actingAs($this->agent->fresh())->test(ShowAccount::class, ['account' => $this->account])
        ->set('reason', 'x')->set('code', $code)->call('reveal')->assertHasNoErrors()->assertSee(PAM_SECRET);
});

it('tells the person when an authenticator app is set up', function (): void {
    Notification::fake();
    $tfa = app(Modules\Core\Application\Authentication\TwoFactorAuthentication::class);
    $secret = (new Google2FA)->generateSecretKey();
    $tfa->enable($this->agent, $secret, (new Google2FA)->getCurrentOtp($secret));

    Notification::assertSentTo($this->agent, Modules\Core\Application\Notifications\SecondFactorAdded::class);
});

it('keeps the shown credential out of the page state', function (): void {
    AccountGrant::query()->create(['account_id' => $this->account->id, 'user_id' => $this->agent->id]);
    $component = Livewire::actingAs($this->agent)->test(ShowAccount::class, ['account' => $this->account])
        ->set('reason', 'x')->set('code', withCode($this->agent))->call('reveal')->assertSee(PAM_SECRET);

    expect(json_encode($component->snapshot))->not->toContain(PAM_SECRET);
    $component->call('hide')->assertDontSee(PAM_SECRET);
});

it('does not open a credential copied onto another account', function (): void {
    $mine = PrivilegedAccount::factory()->create();
    AccountGrant::query()->create(['account_id' => $mine->id, 'user_id' => $this->agent->id]);
    DB::table('pam_accounts')->where('id', $mine->id)->update(['secret_ciphertext' => $this->account->secret_ciphertext]);

    Livewire::actingAs($this->agent)->test(ShowAccount::class, ['account' => $mine->fresh()])
        ->set('reason', 'x')->set('code', withCode($this->agent))->call('reveal')->assertHasErrors('code')->assertDontSee(PAM_SECRET);
});

it('does not let ownership replace the right to use the vault', function (): void {
    $this->account->update(['owner_id' => $this->employee->id]);

    expect($this->employee->can('update', $this->account))->toBeFalse();
    $this->actingAs($this->manager);
    Livewire::test(AccountForm::class, ['account' => $this->account])->set('ownerId', $this->employee->id)->call('save')->assertHasErrors('ownerId');
});

it('tells the person, the owner and other managers when access is given', function (): void {
    Notification::fake();
    $other = pamMember($this, DefaultRole::SecurityOfficer);
    $owner = pamMember($this, DefaultRole::Agent);
    $this->account->update(['owner_id' => $owner->id]);

    Livewire::actingAs($this->manager)->test(ShowAccount::class, ['account' => $this->account])
        ->set('actionCode', withCode($this->manager))->set('grantUserId', $this->agent->id)->call('grant')->assertHasNoErrors();

    Notification::assertSentTo([$this->agent, $other, $owner], AccessGranted::class);
    Notification::assertNotSentTo($this->manager, AccessGranted::class);
});

it('drops access and ownership when someone leaves the organization', function (): void {
    AccountGrant::query()->create(['account_id' => $this->account->id, 'user_id' => $this->agent->id]);
    $owned = PrivilegedAccount::factory()->create(['owner_id' => $this->agent->id]);

    app(Modules\Core\Application\Users\RemoveFromOrganization::class)->handle($this->agent);

    expect(AccountGrant::query()->count())->toBe(0)->and($owned->fresh()->owner_id)->toBeNull();
});

it('shows only this organization in the access log, including deleted accounts', function (): void {
    AccountGrant::query()->create(['account_id' => $this->account->id, 'user_id' => $this->agent->id]);
    Livewire::actingAs($this->agent)->test(ShowAccount::class, ['account' => $this->account])
        ->set('reason', 'Night maintenance')->set('code', withCode($this->agent))->call('reveal');
    $auditor = pamMember($this, DefaultRole::Auditor);

    $this->account->delete();
    Livewire::actingAs($auditor)->test(AccessLog::class)->assertSee('Database admin');

    $other = Organization::factory()->create();
    $auditor->organizations()->attach($other);
    app(OrganizationContext::class)->set($other);
    Livewire::actingAs($auditor)->test(AccessLog::class)->assertDontSee('Night maintenance');
});

it('never writes a credential to the log', function (): void {
    Log::spy();
    app(ChangeSecret::class)->handle($this->account, PAM_SECRET, $this->manager);

    Log::shouldNotHaveReceived('info', fn ($message) => str_contains((string) $message, PAM_SECRET));
});
