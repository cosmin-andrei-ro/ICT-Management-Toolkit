<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the organization context for an authenticated user (route alias
 * "organization"). Guests pass through untouched, leaving the decision to the
 * authentication middleware; with no context, scoped queries return nothing.
 */
final readonly class SetCurrentOrganization
{
    public function __construct(private OrganizationContext $context) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        // Deactivation takes effect on the next request, not at the next login.
        abort_unless($user->is_active, Response::HTTP_FORBIDDEN, __('core::errors.account_inactive'));

        $organization = $this->resolveOrganization($user);

        abort_if($organization === null, Response::HTTP_FORBIDDEN, __('core::errors.no_organization'));

        $this->context->set($organization);

        return $next($request);
    }

    /**
     * The stored current organization is only a preference: membership is checked
     * on every request, so removing a user from an organization takes effect at once.
     */
    private function resolveOrganization(User $user): ?Organization
    {
        $memberships = $user->organizations()->where('is_active', true);

        if ($user->current_organization_id !== null) {
            $current = (clone $memberships)->whereKey($user->current_organization_id)->first();

            if ($current !== null) {
                return $current;
            }
        }

        $fallback = $memberships->orderBy('name')->first();

        $user->forceFill(['current_organization_id' => $fallback?->id])->saveQuietly();

        return $fallback;
    }
}
