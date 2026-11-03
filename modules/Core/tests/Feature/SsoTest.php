<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Jose\Component\KeyManagement\JWKFactory;
use Livewire\Livewire;
use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Application\Sso\HostResolver;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Authorization\Role;
use Modules\Core\Domain\Directory\AuthSource;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Sso\SsoGroupMapping;
use Modules\Core\Domain\Sso\SsoIdentity;
use Modules\Core\Domain\Sso\SsoProvider;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Filament\Resources\SsoGroupMappings\Pages\ManageSsoGroupMappings;
use Modules\Core\Http\Filament\Resources\SsoProviders\Pages\ManageSsoProviders;
use Modules\Core\Tests\Support\FakeIdentityProvider;

beforeEach(function (): void {
    app(SyncPermissions::class)->handle();
    $this->idp = new FakeIdentityProvider;
    $this->organization = Organization::factory()->create();
    app(OrganizationContext::class)->set($this->organization);
    $this->provider = SsoProvider::query()->create([
        'name' => 'Company account',
        'issuer' => FakeIdentityProvider::ISSUER,
        'client_id' => 'toolkit',
        'client_secret' => 'very-secret-value',
        'allowed_domains' => ['company.test'],
    ]);
    $agent = Role::query()->whereNull('organization_id')->where('name', DefaultRole::Agent->value)->sole();
    SsoGroupMapping::query()->create(['provider_id' => $this->provider->id, 'group' => 'it-staff', 'role_id' => $agent->id]);
    app(OrganizationContext::class)->clear();
});

/**
 * Goes to the provider and comes back with the given claims.
 *
 * @param  array<string, mixed>  $claims
 */
function ssoSignIn(object $test, array $claims, ?string $state = null): TestResponse
{
    $redirect = $test->get(route('login.sso', $test->provider->id));
    $location = (string) $redirect->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    $test->idp->willAnswer('toolkit', is_string($query['nonce'] ?? null) ? $query['nonce'] : '', $claims);

    return $test->get(route('login.sso.callback', ['provider' => $test->provider->id, 'state' => $state ?? $query['state'], 'code' => 'code-1']));
}

it('sends people to the provider with PKCE, state and nonce, and keeps the secret out of sight', function (): void {
    $this->get(route('login'))->assertSee('Sign in with Company account');
    $response = $this->get(route('login.sso', $this->provider->id))->assertRedirect();
    parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);

    expect($response->headers->get('Location'))->toStartWith(FakeIdentityProvider::ISSUER.'/protocol/openid-connect/auth?')
        ->and($query)->toMatchArray(['response_type' => 'code', 'client_id' => 'toolkit', 'code_challenge_method' => 'S256', 'redirect_uri' => route('login.sso.callback', $this->provider->id)])
        ->and($query)->toHaveKeys(['state', 'nonce', 'code_challenge']);

    expect(DB::table('core_sso_providers')->value('client_secret'))->not->toContain('very-secret-value')
        ->and($this->provider->toArray())->not->toHaveKey('client_secret')
        ->and(json_encode(AuditLog::query()->where('auditable_id', $this->provider->id)->get()))->not->toContain('very-secret-value');
});

it('creates the account at first sign-in with the roles of its mapped groups, trusting the provider\'s second factor', function (): void {
    ssoSignIn($this, ['sub' => 'user-1', 'email' => 'Ana@Company.test', 'name' => 'Ana Pop', 'groups' => ['it-staff'], 'amr' => ['pwd', 'mfa']])
        ->assertRedirect(route('home'));

    $user = User::query()->where('email', 'ana@company.test')->sole();
    $this->assertAuthenticatedAs($user);
    expect($user->auth_source)->toBe(AuthSource::Sso)->and($user->password)->toBeNull()
        ->and(app(OrganizationContext::class)->runAs($this->organization, fn () => $user->fresh()?->hasRole(DefaultRole::Agent->value)))->toBeTrue();

    // An IT role normally requires the toolkit's own second factor; the provider asked for one.
    $this->get(route('home'))->assertOk();

    // Signing in again: same account, roles follow the groups.
    auth()->logout();
    ssoSignIn($this, ['sub' => 'user-1', 'email' => 'ana@company.test', 'groups' => []])->assertRedirect(route('home'));
    expect(User::query()->where('email', 'ana@company.test')->count())->toBe(1)
        ->and(app(OrganizationContext::class)->runAs($this->organization, fn () => $user->fresh()?->getRoleNames()->all()))->toBe([DefaultRole::Requester->value]);
});

it('asks for the toolkit\'s own code when the provider did not ask for a second factor', function (): void {
    $user = User::factory()->memberOf($this->organization)->withTwoFactor()->create(['email' => 'dan@company.test']);

    ssoSignIn($this, ['sub' => 'user-2', 'email' => 'dan@company.test', 'amr' => ['pwd']])->assertRedirect(route('login.challenge'));
    $this->assertGuest();
    expect(SsoIdentity::query()->where('user_id', $user->id)->exists())->toBeTrue()
        ->and($user->fresh()?->auth_source)->toBe(AuthSource::Local);
});

it('keeps a linked local account\'s own second factor, even when the provider is trusted for it', function (): void {
    $admin = User::factory()->memberOf($this->organization)->withTwoFactor()->create(['email' => 'admin@company.test']);

    // Whoever set up the provider could otherwise sign in as an existing administrator without their code.
    ssoSignIn($this, ['sub' => 'admin', 'email' => 'admin@company.test', 'amr' => ['pwd', 'mfa']])->assertRedirect(route('login.challenge'));
    $this->assertGuest();
    expect(SsoIdentity::query()->where('user_id', $admin->id)->exists())->toBeTrue();
});

it('refuses an identity already linked once its account also belongs to another organization', function (): void {
    ssoSignIn($this, ['sub' => 'user-1', 'email' => 'ana@company.test', 'amr' => ['mfa']])->assertRedirect(route('home'));
    auth()->logout();
    User::query()->where('email', 'ana@company.test')->sole()->organizations()->attach(Organization::factory()->create()->id);

    ssoSignIn($this, ['sub' => 'user-1', 'email' => 'ana@company.test', 'amr' => ['mfa']])->assertRedirect(route('login'))->assertSessionHas('sso_error');
    $this->assertGuest();
    expect(AuditLog::query()->where('event', AuditEvent::LoginDenied)->pluck('new_values')->map(fn ($values) => $values['reason'] ?? null)->all())->toContain('other_organizations');
});

it('refuses other domains and accounts of other organizations, and records why', function (): void {
    ssoSignIn($this, ['sub' => 'x', 'email' => 'mallory@elsewhere.test'])->assertRedirect(route('login'))->assertSessionHas('sso_error');

    // An account that also belongs to another organization cannot be taken over through this provider.
    $shared = User::factory()->memberOf($this->organization)->create(['email' => 'boss@company.test']);
    $shared->organizations()->attach(Organization::factory()->create()->id);
    ssoSignIn($this, ['sub' => 'y', 'email' => 'boss@company.test', 'amr' => ['mfa']])->assertRedirect(route('login'));

    $this->assertGuest();
    expect(AuditLog::query()->where('event', AuditEvent::LoginDenied)->pluck('new_values')->map(fn ($values) => $values['reason'] ?? null)->all())
        ->toContain('domain')->toContain('account_exists');
});

it('rejects tokens that are forged, replayed, expired, for someone else, or signed with a shared secret', function (array $claims, ?string $state): void {
    ssoSignIn($this, ['sub' => 'user-3', 'email' => 'eve@company.test', ...$claims], $state)->assertRedirect(route('login'))->assertSessionHas('sso_error');
    $this->assertGuest();
})->with([
    'another audience' => [['aud' => 'another-app'], null],
    'expired' => [['exp' => time() - 600], null],
    'another issuer' => [['iss' => 'https://evil.example.test'], null],
    'wrong nonce' => [['nonce' => 'guessed'], null],
    'wrong state' => [[], 'forged-state'],
]);

it('rejects a token signed with another key or with an HMAC of the public key', function (): void {
    $redirect = $this->get(route('login.sso', $this->provider->id));
    parse_str((string) parse_url((string) $redirect->headers->get('Location'), PHP_URL_QUERY), $query);
    $this->idp->willAnswer('toolkit', $query['nonce'], ['sub' => 'z', 'email' => 'eve@company.test'], JWKFactory::createRSAKey(2048, ['kid' => 'key-1', 'alg' => 'RS256']));
    $this->get(route('login.sso.callback', ['provider' => $this->provider->id, 'state' => $query['state'], 'code' => 'c']))->assertRedirect(route('login'));

    $redirect = $this->get(route('login.sso', $this->provider->id));
    parse_str((string) parse_url((string) $redirect->headers->get('Location'), PHP_URL_QUERY), $query);
    $this->idp->willAnswer('toolkit', $query['nonce'], ['sub' => 'z', 'email' => 'eve@company.test'], JWKFactory::createFromSecret('public-key-as-secret-'.str_repeat('x', 32), ['kid' => 'key-1']), 'HS256');
    $this->get(route('login.sso.callback', ['provider' => $this->provider->id, 'state' => $query['state'], 'code' => 'c']))->assertRedirect(route('login'));

    $this->assertGuest();
});

it('never connects to providers on internal addresses, nor to disabled providers', function (): void {
    app()->instance(HostResolver::class, new class implements HostResolver
    {
        public function resolve(string $host): array
        {
            return ['169.254.169.254'];
        }
    });
    $this->get(route('login.sso', $this->provider->id))->assertRedirect(route('login'))->assertSessionHas('sso_error');

    $this->provider->forceFill(['is_enabled' => false])->saveQuietly();
    $this->get(route('login.sso', $this->provider->id))->assertNotFound();
});

it('lets administrators set up providers and mappings of their organization only', function (): void {
    Filament::setCurrentPanel('admin');
    $admin = User::factory()->memberOf($this->organization)->withTwoFactor()->create();
    $agent = User::factory()->memberOf($this->organization)->withTwoFactor()->create();
    app(OrganizationContext::class)->runAs($this->organization, function () use ($admin, $agent): void {
        $admin->assignRole(DefaultRole::Administrator->value);
        $agent->assignRole(DefaultRole::Agent->value);
    });
    $other = Organization::factory()->create();
    $foreign = app(OrganizationContext::class)->runAs($other, fn () => SsoProvider::query()->create([
        'name' => 'Other', 'issuer' => 'https://other.example.test', 'client_id' => 'x', 'client_secret' => 'y', 'allowed_domains' => ['other.test'],
    ]));

    $this->actingAs($admin);
    app(OrganizationContext::class)->set($this->organization);
    Livewire::test(ManageSsoProviders::class)
        ->assertCanSeeTableRecords([$this->provider])->assertCanNotSeeTableRecords([$foreign])
        ->callAction('create', data: [
            'name' => 'Microsoft', 'issuer' => 'https://login.example.test/tenant/v2.0', 'client_id' => 'app', 'client_secret' => 'secret-2',
            'allowed_domains' => ['Company.test'], 'scopes' => 'openid email profile', 'groups_claim' => 'groups', 'trust_mfa' => true, 'is_enabled' => true,
        ])->assertHasNoFormErrors()
        ->callAction('create', data: ['name' => 'Bad', 'issuer' => 'https://x.test', 'client_id' => 'a', 'client_secret' => 'b', 'allowed_domains' => ['not a domain'], 'scopes' => 'email', 'groups_claim' => 'groups'])
        ->assertHasFormErrors(['scopes']);
    expect(SsoProvider::query()->where('name', 'Microsoft')->sole()->allowed_domains)->toBe(['company.test']);

    Livewire::test(ManageSsoGroupMappings::class)->assertCanSeeTableRecords(SsoGroupMapping::query()->get());

    $this->actingAs($agent);
    Livewire::test(ManageSsoProviders::class)->assertForbidden();
});
