<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Http\Livewire\Auth\Login;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->user = User::factory()->memberOf($this->organization)->create([
        'email' => 'jane@example.com',
        'password' => 'correct-horse-battery',
    ]);
});

function attemptLogin(string $email, string $password): Testable
{
    return Livewire::test(Login::class)
        ->set('login', $email)
        ->set('password', $password)
        ->call('submit');
}

it('shows the login page to guests', function (): void {
    $this->get(route('login'))
        ->assertOk()
        ->assertSeeLivewire(Login::class)
        ->assertSee(config('app.source_url'));
});

it('redirects guests to the login page', function (): void {
    $this->get(route('home'))->assertRedirect(route('login'));
});

it('signs in with valid credentials and records the login', function (): void {
    attemptLogin('jane@example.com', 'correct-horse-battery')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($this->user);

    expect($this->user->fresh()?->last_login_at)->not->toBeNull()
        ->and(AuditLog::query()->where('event', AuditEvent::Login)->where('user_id', $this->user->id)->exists())->toBeTrue();
});

it('matches the email case-insensitively', function (): void {
    attemptLogin(' JANE@Example.com ', 'correct-horse-battery')->assertHasNoErrors();

    $this->assertAuthenticatedAs($this->user);
});

it('rejects a wrong password without echoing it back', function (): void {
    attemptLogin('jane@example.com', 'wrong-password')
        ->assertHasErrors(['login'])
        ->assertSet('password', '');

    $this->assertGuest();

    $entry = AuditLog::query()->where('event', AuditEvent::LoginFailed)->sole();
    expect($entry->auditable_id)->toBe($this->user->id)
        ->and($entry->new_values)->toBe(['email' => 'jane@example.com']);
});

it('gives the same answer for unknown and inactive accounts', function (): void {
    $this->user->forceFill(['is_active' => false])->save();

    $inactive = attemptLogin('jane@example.com', 'correct-horse-battery')->errors()->get('login');
    $unknown = attemptLogin('nobody@example.com', 'correct-horse-battery')->errors()->get('login');

    expect($inactive)->toBe($unknown)->toBe([__('auth.failed')]);
    $this->assertGuest();
});

it('locks out after five failed attempts and records the lockout', function (): void {
    foreach (range(1, 5) as $_) {
        attemptLogin('jane@example.com', 'wrong-password');
    }

    attemptLogin('jane@example.com', 'correct-horse-battery')
        ->assertHasErrors(['login']);

    $this->assertGuest();
    expect(AuditLog::query()->where('event', AuditEvent::Lockout)->exists())->toBeTrue();
});

it('validates the input', function (): void {
    Livewire::test(Login::class)
        ->set('login', '')
        ->set('password', '')
        ->call('submit')
        ->assertHasErrors(['login' => 'required', 'password' => 'required']);
});

it('logs out, invalidates the session and records it', function (): void {
    $this->actingAs($this->user)
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
    expect(AuditLog::query()->where('event', AuditEvent::Logout)->where('user_id', $this->user->id)->exists())->toBeTrue();
});

it('lets a user without any organization log out', function (): void {
    $this->user->organizations()->detach();

    $this->actingAs($this->user)->post(route('logout'))->assertRedirect(route('login'));
});

it('shows the home page to members of an organization', function (): void {
    $this->actingAs($this->user)
        ->get(route('home'))
        ->assertOk()
        ->assertSee($this->user->name);
});
