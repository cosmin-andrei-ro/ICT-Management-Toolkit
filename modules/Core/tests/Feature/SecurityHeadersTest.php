<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * @return array<string, string>
 */
function cspDirectives(string $header): array
{
    $directives = [];

    foreach (explode(';', $header) as $part) {
        [$name, $value] = array_pad(explode(' ', trim($part), 2), 2, '');
        $directives[$name] = $value;
    }

    return $directives;
}

it('sends the security headers', function (): void {
    $this->get(route('login'))
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
        ->assertHeaderMissing('Strict-Transport-Security');
});

it('sends HSTS over HTTPS only', function (): void {
    $this->get('https://localhost/login')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('uses a nonce-based CSP without eval in the application', function (): void {
    $response = $this->get(route('login'));
    $csp = cspDirectives((string) $response->headers->get('Content-Security-Policy'));

    preg_match("/'nonce-([^']+)'/", $csp['script-src'], $matches);

    expect($csp['script-src'])->not->toContain('unsafe-eval')->not->toContain('unsafe-inline')
        ->and($csp['object-src'])->toBe("'none'")
        ->and($csp['frame-ancestors'])->toBe("'none'")
        ->and($matches[1] ?? null)->not->toBeNull();

    // Every inline script carries the nonce, and Livewire ships its eval-free Alpine build.
    $response->assertSee('nonce="'.$matches[1].'"', escape: false)
        ->assertSee('livewire.csp', escape: false);
});

it('generates a fresh nonce per request', function (): void {
    $first = $this->get(route('login'))->headers->get('Content-Security-Policy');
    $second = $this->get(route('login'))->headers->get('Content-Security-Policy');

    expect($first)->not->toBe($second);
});

describe('admin panel', function (): void {
    beforeEach(function (): void {
        app(SyncPermissions::class)->handle();
        $this->organization = Organization::factory()->create();
    });

    function memberWithRole(Organization $organization, DefaultRole $role): User
    {
        $user = User::factory()->memberOf($organization)->create();
        app(OrganizationContext::class)->runAs($organization, fn () => $user->assignRole($role->value));

        return $user;
    }

    it('lets administrators in, with eval allowed only there', function (): void {
        $admin = memberWithRole($this->organization, DefaultRole::Administrator);

        $response = $this->actingAs($admin)->get('/admin')->assertOk();
        $csp = cspDirectives((string) $response->headers->get('Content-Security-Policy'));

        expect($csp['script-src'])->toContain("'unsafe-eval'");
        $response->assertDontSee('livewire.csp', escape: false);

        // The relaxed mode must not leak into the next request.
        expect(config('livewire.csp_safe'))->toBeTrue();
    });

    it('forbids members without the admin permission', function (): void {
        $agent = memberWithRole($this->organization, DefaultRole::Agent);

        $this->actingAs($agent)->get('/admin')->assertForbidden();
    });

    it('forbids inactive administrators', function (): void {
        $admin = memberWithRole($this->organization, DefaultRole::Administrator);
        $admin->forceFill(['is_active' => false])->save();

        $this->actingAs($admin)->get('/admin')->assertForbidden();
    });

    it('sends guests to the application login', function (): void {
        $this->get('/admin')->assertRedirect(route('login'));
    });
});
