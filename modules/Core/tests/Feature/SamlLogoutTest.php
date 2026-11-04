<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Testing\TestResponse;
use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Directory\AuthSource;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Sso\SsoIdentity;
use Modules\Core\Domain\Sso\SsoProvider;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Controllers\LogoutController;
use Modules\Core\Http\Middleware\SetCurrentOrganization;
use Modules\Core\Tests\Support\FakeSamlIdentityProvider;

const SAML_LOGOUT_URL = 'https://idp.example.test/saml/logout';

beforeEach(function (): void {
    app(SyncPermissions::class)->handle();
    $this->idp = new FakeSamlIdentityProvider;
    $this->organization = Organization::factory()->create();
    app(OrganizationContext::class)->set($this->organization);
    $this->provider = SsoProvider::query()->create([
        'protocol' => 'saml',
        'name' => 'Company IdP',
        'issuer' => FakeSamlIdentityProvider::ENTITY_ID,
        'sso_url' => FakeSamlIdentityProvider::SSO_URL,
        'slo_url' => SAML_LOGOUT_URL,
        'certificate' => $this->idp->certificate,
        'allowed_domains' => ['company.test'],
    ]);
    $this->person = User::factory()->create(['auth_source' => AuthSource::Sso, 'password' => null]);
    $this->person->organizations()->attach($this->organization);
    (new SsoIdentity)->forceFill(['provider_id' => $this->provider->id, 'subject' => 'ana@company.test', 'user_id' => $this->person->id])->save();
    app(OrganizationContext::class)->clear();
});

afterEach(function (): void {
    $_GET = [];
    unset($_SERVER['QUERY_STRING']);
});

/**
 * The library reads the signed query from the server globals, as in a real request.
 */
function visitSamlLogout(object $test, string $query): TestResponse
{
    parse_str($query, $_GET);
    $_SERVER['QUERY_STRING'] = $query;

    return $test->get(route('login.saml.slo', $test->provider->id).'?'.$query);
}

it('sends the person to sign out at the provider, after ending the session here', function (): void {
    $response = $this->actingAs($this->person)
        ->withSession([LogoutController::SAML_SESSION => ['provider' => $this->provider->id, 'name_id' => 'ana@company.test', 'session_index' => '_s1']])
        ->post(route('logout'))->assertOk()->assertSee(__('core::sso.signing_out.line'));

    $this->assertGuest();
    $refresh = (string) $response->headers->get('Refresh');
    expect($refresh)->toContain(SAML_LOGOUT_URL.'?SAMLRequest=')->toContain('Signature=');
    parse_str((string) parse_url(substr($refresh, strlen('0; url=')), PHP_URL_QUERY), $query);
    $xml = (string) gzinflate((string) base64_decode(is_string($query['SAMLRequest'] ?? null) ? $query['SAMLRequest'] : '', true));
    expect($xml)->toContain('ana@company.test')->toContain('_s1');
});

it('just signs out when the provider has no single logout, or the session did not come from it', function (): void {
    $this->actingAs($this->person)->post(route('logout'))->assertRedirect(route('login'));
    $this->assertGuest();

    $this->provider->update(['slo_url' => null]);
    $this->actingAs($this->person)
        ->withSession([LogoutController::SAML_SESSION => ['provider' => $this->provider->id, 'name_id' => 'ana@company.test', 'session_index' => null]])
        ->post(route('logout'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('ends the person\'s sessions on a logout request signed by the provider, and answers it', function (): void {
    $query = $this->idp->logoutRequest(route('login.saml.slo', $this->provider->id), 'ana@company.test');
    $response = visitSamlLogout($this, $query)->assertRedirect();

    expect((string) $response->headers->get('Location'))->toStartWith(SAML_LOGOUT_URL.'?SAMLResponse=')->toContain('Signature=')
        ->and($this->person->fresh()->sessions_revoked_at)->not->toBeNull()
        ->and(AuditLog::query()->withoutGlobalScopes()->where('event', AuditEvent::Logout->value)->where('user_id', $this->person->id)->exists())->toBeTrue();

    $this->actingAs($this->person->fresh())->withSession([SetCurrentOrganization::SIGNED_IN_AT => time() - 60])
        ->get(route('home'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('keeps a session begun after the provider\'s logout', function (): void {
    $this->person->forceFill(['sessions_revoked_at' => now()->subMinute()])->saveQuietly();

    $this->actingAs($this->person->fresh())->withSession([SetCurrentOrganization::SIGNED_IN_AT => time()])
        ->get(route('home'))->assertOk();
});

it('refuses a logout request without a valid signature', function (array $overrides): void {
    $overrides = array_map(fn (mixed $value): mixed => $value === 'other' ? $this->idp->otherKey() : $value, $overrides);
    $query = $this->idp->logoutRequest(route('login.saml.slo', $this->provider->id), 'ana@company.test', $overrides);

    visitSamlLogout($this, $query)->assertStatus(400);
    expect($this->person->fresh()->sessions_revoked_at)->toBeNull();
})->with([
    'unsigned' => [['sign' => false]],
    'signed with another key' => [['signingKey' => 'other']],
    'from another issuer' => [['issuer' => 'https://evil.example.test/saml']],
]);

it('answers the same for someone without an account, and touches nobody', function (): void {
    $query = $this->idp->logoutRequest(route('login.saml.slo', $this->provider->id), 'nobody@company.test');
    $response = visitSamlLogout($this, $query)->assertRedirect();

    expect((string) $response->headers->get('Location'))->toStartWith(SAML_LOGOUT_URL.'?SAMLResponse=')
        ->and($this->person->fresh()->sessions_revoked_at)->toBeNull();
});

it('offers no single logout on a provider without it, and ignores the provider\'s answer otherwise', function (): void {
    $this->get(route('login.saml.slo', $this->provider->id).'?SAMLResponse=x')->assertRedirect(route('login'));

    $this->provider->update(['slo_url' => null]);
    $this->get(route('login.saml.slo', $this->provider->id))->assertNotFound();
});
