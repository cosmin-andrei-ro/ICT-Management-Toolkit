<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Filament\Facades\Filament;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;
use Modules\Core\Application\Api\IssueApiToken;
use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Filament\Resources\ApiTokens\Pages\ManageApiTokens;
use Modules\Core\Http\Livewire\Account\ApiTokens;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function (): void {
    app(SyncPermissions::class)->handle();
    $this->organization = Organization::factory()->create();
    app(OrganizationContext::class)->set($this->organization);
    $this->agent = User::factory()->memberOf($this->organization)->withTwoFactor()->create();
    $this->agent->assignRole(DefaultRole::Agent->value);
});

function tokenCode(int $offset = 0): string
{
    return (new Google2FA)->oathTotp('JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP', (int) floor(time() / 30) + $offset);
}

it('is for IT staff; requesters cannot issue tokens', function (): void {
    $requester = User::factory()->memberOf($this->organization)->create();
    $requester->assignRole(DefaultRole::Requester->value);

    $this->actingAs($this->agent)->get(route('account.api-tokens'))->assertOk()->assertSee('API tokens');
    $this->actingAs($requester)->get(route('account.api-tokens'))->assertForbidden();
});

it('issues a token for the current organization after a fresh code, and shows it once', function (): void {
    $this->actingAs($this->agent);

    Livewire::test(ApiTokens::class)
        ->set('name', 'Monitoring')->set('abilities', ['tickets:read'])->set('days', 30)->set('code', '000000')
        ->call('create')->assertHasErrors(['code'])
        ->set('abilities', ['root:everything'])->set('code', tokenCode())
        ->call('create')->assertHasErrors(['abilities.0']);
    expect(AuditLog::query()->where('event', AuditEvent::MfaFailed)->where('user_id', $this->agent->id)->exists())->toBeTrue();

    $page = Livewire::test(ApiTokens::class)
        ->set('name', 'Monitoring')->set('abilities', ['tickets:read'])->set('days', 30)->set('code', tokenCode(1))
        ->call('create')->assertHasNoErrors();

    $plain = $page->get('plainToken');
    $token = PersonalAccessToken::findToken((string) $plain);
    expect($plain)->toMatch('/^\d+\|ictmt_/')
        ->and($token?->getAttribute('organization_id'))->toBe($this->organization->id)
        ->and($token?->abilities)->toBe(['tickets:read'])
        ->and($token?->expires_at?->isSameDay(now()->addDays(30)))->toBeTrue();

    $page->call('forget')->assertSet('plainToken', null)->assertSee('Monitoring')->assertDontSee((string) $plain);
});

it('revokes a token, and the revocation is recorded', function (): void {
    $issued = app(IssueApiToken::class)->handle($this->agent, 'Old script', ['tickets:read']);
    $this->actingAs($this->agent);

    Livewire::test(ApiTokens::class)->call('revoke', (string) $issued->accessToken->getKey());

    expect(PersonalAccessToken::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('new_values->api_token_revoked->name', 'Old script')->exists())->toBeTrue();
});

it('lets administrators see and revoke the tokens of their organization only', function (): void {
    Filament::setCurrentPanel('admin');
    $admin = User::factory()->memberOf($this->organization)->withTwoFactor()->create();
    $admin->assignRole(DefaultRole::Administrator->value);
    $ours = app(IssueApiToken::class)->handle($this->agent, 'Our integration', ['tickets:read']);
    $other = Organization::factory()->create();
    $stranger = User::factory()->memberOf($other)->create();
    app(IssueApiToken::class)->handle($stranger, 'Their integration', ['tickets:read'], null, $other->id);

    $this->actingAs($this->agent)->get('/admin/api-tokens')->assertForbidden();
    $this->actingAs($admin);
    Livewire::test(ManageApiTokens::class)->assertSee('Our integration')->assertDontSee('Their integration')
        ->callTableAction('revoke', $ours->accessToken);

    expect(PersonalAccessToken::query()->pluck('name')->all())->toBe(['Their integration']);
});
