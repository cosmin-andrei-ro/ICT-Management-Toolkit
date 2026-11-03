<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Livewire\Account;

use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Application\Api\ApiAbility;
use Modules\Core\Application\Api\IssueApiToken;
use Modules\Core\Application\Authentication\TwoFactorAuthentication;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * A person's own API tokens (ADR 0061): issued for the current organization,
 * with chosen abilities and an expiry, after a fresh authenticator code. The
 * token is shown once; afterwards only its name and use are.
 */
#[Layout('core::components.layouts.app')]
final class ApiTokens extends Component
{
    private const int CODES_PER_MINUTE = 5;

    public string $name = '';

    /** @var list<string> */
    public array $abilities = [];

    public int $days = 90;

    public string $code = '';

    /** Shown once, right after issuing. */
    public ?string $plainToken = null;

    public function mount(): void
    {
        $this->authorize('core.api.use');
    }

    public function create(IssueApiToken $issue, TwoFactorAuthentication $twoFactor, RateLimiter $limiter, AuditTrail $trail, OrganizationContext $context): void
    {
        $this->authorize('core.api.use');
        $user = $this->user();
        $this->plainToken = null;

        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['string', Rule::in(ApiAbility::all())],
            'days' => ['required', 'integer', 'min:1', 'max:365'],
            'code' => ['required', 'string', 'max:32'],
        ]);

        $throttle = 'api-token:'.$user->id;

        if ($limiter->tooManyAttempts($throttle, self::CODES_PER_MINUTE)) {
            throw ValidationException::withMessages(['code' => __('auth.throttle', ['seconds' => $limiter->availableIn($throttle)])]);
        }

        // Issuing a credential is re-authenticated (CLAUDE.md §6).
        if (! $twoFactor->verify($user, $this->code)) {
            $limiter->hit($throttle, 60);
            $trail->record(AuditEvent::MfaFailed, $user, null, ['purpose' => 'issue_api_token'], $user->id);

            throw ValidationException::withMessages(['code' => __('core::api_tokens.errors.code')]);
        }

        $limiter->clear($throttle);
        $token = $issue->handle($user, trim($this->name), $this->abilities, now()->addDays($this->days), $context->requireId());
        $this->plainToken = $token->plainTextToken;
        $this->reset('name', 'abilities', 'code');
        $this->days = 90;
    }

    public function revoke(string $tokenId, IssueApiToken $issue): void
    {
        $this->authorize('core.api.use');
        $user = $this->user();
        $issue->revoke($user, $tokenId, $user);
        $this->plainToken = null;
    }

    public function forget(): void
    {
        $this->plainToken = null;
    }

    public function render(OrganizationContext $context): View
    {
        $user = $this->user();

        return view('core::livewire.account.api-tokens', [
            'tokens' => $user->tokens()->where(fn ($query) => $query->where('organization_id', $context->requireId())->orWhereNull('organization_id'))->latest()->get(),
            'available' => ApiAbility::descriptions(),
            'twoFactor' => $user->two_factor_confirmed_at !== null,
            'timezone' => config()->string('app.display_timezone'),
        ])->title(__('core::api_tokens.title'));
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
