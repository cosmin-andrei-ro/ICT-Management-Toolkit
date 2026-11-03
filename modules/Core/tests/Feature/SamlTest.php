<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Application\Sso\Saml;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Authorization\Role;
use Modules\Core\Domain\Directory\AuthSource;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Sso\SsoGroupMapping;
use Modules\Core\Domain\Sso\SsoProvider;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Tests\Support\FakeSamlIdentityProvider;

beforeEach(function (): void {
    config(['app.url' => 'http://localhost']);
    app(SyncPermissions::class)->handle();
    $this->idp = new FakeSamlIdentityProvider;
    $this->organization = Organization::factory()->create();
    app(OrganizationContext::class)->set($this->organization);
    $this->provider = SsoProvider::query()->create([
        'protocol' => 'saml',
        'name' => 'ADFS',
        'issuer' => FakeSamlIdentityProvider::ENTITY_ID,
        'sso_url' => FakeSamlIdentityProvider::SSO_URL,
        'certificate' => $this->idp->certificate,
        'allowed_domains' => ['company.test'],
        'groups_claim' => 'http://schemas.microsoft.com/ws/2008/06/identity/claims/groups',
    ]);
    $agent = Role::query()->whereNull('organization_id')->where('name', DefaultRole::Agent->value)->sole();
    SsoGroupMapping::query()->create(['provider_id' => $this->provider->id, 'group' => 'IT Staff', 'role_id' => $agent->id]);
    app(OrganizationContext::class)->clear();
});

/**
 * The ID of the AuthnRequest the toolkit sent when the person clicked the button.
 */
function samlRequestId(object $test): string
{
    $response = $test->get(route('login.saml', $test->provider->id))->assertRedirect();
    $location = (string) $response->headers->get('Location');
    expect($location)->toStartWith(FakeSamlIdentityProvider::SSO_URL.'?SAMLRequest=');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    $xml = gzinflate(base64_decode(is_string($query['SAMLRequest'] ?? null) ? $query['SAMLRequest'] : '', true) ?: '');
    preg_match('/ ID="([^"]+)"/', (string) $xml, $match);
    $requestId = $match[1] ?? '';
    // This browser started the sign-in: it keeps the cookie that says so.
    $response->assertCookie(Saml::browserCookie($requestId));
    $test->withCookie(Saml::browserCookie($requestId), '1');

    return $requestId;
}

/**
 * @param  array<string, list<string>>  $attributes
 * @param  array<string, mixed>  $overrides
 */
function samlAnswer(object $test, string $requestId, array $attributes, array $overrides = []): string
{
    return $test->idp->response($requestId, route('login.saml.acs', $test->provider->id), route('login.saml.metadata', $test->provider->id), 'ana@company.test', $attributes, $overrides);
}

it('publishes the service provider metadata to give the identity provider', function (): void {
    $this->get(route('login.saml.metadata', $this->provider->id))->assertOk()
        ->assertHeader('Content-Type', 'application/samlmetadata+xml')
        ->assertSee('entityID="'.route('login.saml.metadata', $this->provider->id).'"', false)
        ->assertSee(route('login.saml.acs', $this->provider->id), false);
    $this->get(route('login'))->assertSee('Sign in with ADFS');
});

it('signs in a new person from a signed assertion, with roles from groups and the provider\'s second factor', function (): void {
    $answer = samlAnswer($this, samlRequestId($this), [
        'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress' => ['Ana@Company.test'],
        'http://schemas.microsoft.com/identity/claims/displayname' => ['Ana Pop'],
        'http://schemas.microsoft.com/ws/2008/06/identity/claims/groups' => ['IT Staff'],
    ], ['authnContext' => 'http://schemas.microsoft.com/claims/multipleauthn']);

    // Posted by the provider's page: no CSRF token.
    $this->post(route('login.saml.acs', $this->provider->id), ['SAMLResponse' => $answer])->assertRedirect(route('home'));

    $user = User::query()->where('email', 'ana@company.test')->sole();
    $this->assertAuthenticatedAs($user);
    expect($user->auth_source)->toBe(AuthSource::Sso)->and($user->name)->toBe('Ana Pop')
        ->and(app(OrganizationContext::class)->runAs($this->organization, fn () => $user->fresh()?->hasRole(DefaultRole::Agent->value)))->toBeTrue();
    $this->get(route('home'))->assertOk();
});

it('refuses replayed, unsolicited, unsigned, forged and mis-addressed answers', function (?callable $tamper): void {
    $requestId = samlRequestId($this);
    $attributes = ['http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress' => ['ana@company.test']];
    $answer = $tamper !== null ? $tamper($this, $requestId, $attributes) : samlAnswer($this, $requestId, $attributes);

    if ($tamper === null) {
        // Accepted once; the same answer posted again is a replay.
        $this->post(route('login.saml.acs', $this->provider->id), ['SAMLResponse' => $answer])->assertRedirect(route('home'));
        auth()->logout();
    }

    $this->post(route('login.saml.acs', $this->provider->id), ['SAMLResponse' => $answer])->assertRedirect(route('login'))->assertSessionHas('sso_error');
    $this->assertGuest();
})->with([
    'replayed' => [null],
    'unsolicited' => [fn ($test, $id, $attributes) => samlAnswer($test, '_never-asked', $attributes)],
    'unsigned' => [fn ($test, $id, $attributes) => samlAnswer($test, $id, $attributes, ['sign' => false])],
    'signed with another key' => [fn ($test, $id, $attributes) => samlAnswer($test, $id, $attributes, ['signingKey' => $test->idp->otherKey()])],
    'for another service' => [fn ($test, $id, $attributes) => samlAnswer($test, $id, $attributes, ['audience' => 'https://other.example.test'])],
    'to another address' => [fn ($test, $id, $attributes) => samlAnswer($test, $id, $attributes, ['destination' => 'https://evil.example.test/acs'])],
    'from another issuer' => [fn ($test, $id, $attributes) => samlAnswer($test, $id, $attributes, ['issuer' => 'https://evil.example.test'])],
    'expired' => [fn ($test, $id, $attributes) => samlAnswer($test, $id, $attributes, ['notOnOrAfter' => time() - 600])],
]);

it('takes the provider\'s second factor only from the signed assertion', function (): void {
    $injected = '<samlp:Extensions><saml:AuthnStatement AuthnInstant="'.gmdate('Y-m-d\\TH:i:s\\Z').'"><saml:AuthnContext>'
        .'<saml:AuthnContextClassRef>http://schemas.microsoft.com/claims/multipleauthn</saml:AuthnContextClassRef></saml:AuthnContext></saml:AuthnStatement></samlp:Extensions>';
    $answer = samlAnswer($this, samlRequestId($this), [
        'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress' => ['ana@company.test'],
        'http://schemas.microsoft.com/ws/2008/06/identity/claims/groups' => ['IT Staff'],
    ], ['extensions' => $injected]);

    // A password-only sign-in at the provider: the toolkit still asks for its own second factor.
    $this->post(route('login.saml.acs', $this->provider->id), ['SAMLResponse' => $answer])->assertRedirect();
    $this->get(route('home'))->assertRedirect();
});

it('refuses a valid answer posted from a browser that did not ask for it', function (): void {
    // The attacker's own valid answer, auto-submitted from a page to a victim's browser.
    $requestId = samlRequestId($this);
    $answer = samlAnswer($this, $requestId, ['http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress' => ['ana@company.test']], ['authnContext' => 'http://schemas.microsoft.com/claims/multipleauthn']);
    $this->defaultCookies = [];

    $this->post(route('login.saml.acs', $this->provider->id), ['SAMLResponse' => $answer])->assertRedirect(route('login'))->assertSessionHas('sso_error');

    $this->assertGuest();
    expect(User::query()->where('email', 'ana@company.test')->exists())->toBeFalse();
});

it('refuses an assertion altered after signing', function (): void {
    $xml = base64_decode(samlAnswer($this, samlRequestId($this), ['http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress' => ['ana@company.test']]));
    $forged = base64_encode(str_replace('ana@company.test', 'boss@company.test', (string) $xml));

    $this->post(route('login.saml.acs', $this->provider->id), ['SAMLResponse' => $forged])->assertRedirect(route('login'));
    $this->assertGuest();
    expect(User::query()->where('email', 'boss@company.test')->exists())->toBeFalse();
});

it('applies the same account rules as OpenID Connect', function (): void {
    $answer = samlAnswer($this, samlRequestId($this), ['http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress' => ['mallory@elsewhere.test']]);
    $this->post(route('login.saml.acs', $this->provider->id), ['SAMLResponse' => $answer])->assertRedirect(route('login'));

    expect(AuditLog::query()->where('event', AuditEvent::LoginDenied)->get()->map(fn (AuditLog $log) => $log->new_values['reason'] ?? null)->all())->toBe(['domain']);
});
