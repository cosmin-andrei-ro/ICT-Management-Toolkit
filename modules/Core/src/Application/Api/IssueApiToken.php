<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Api;

use DateTimeInterface;
use InvalidArgumentException;
use Laravel\Sanctum\NewAccessToken;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Models\User;

/**
 * Issues a personal API token with explicit abilities. Tokens act as their user:
 * every request is still checked against the user's permissions, so abilities
 * can only narrow what the user may already do.
 */
final readonly class IssueApiToken
{
    public function __construct(private AuditTrail $trail) {}

    /**
     * @param  list<string>  $abilities
     * @param  ?string  $organizationId  Where the token works; the user's current organization when null.
     */
    public function handle(User $user, string $name, array $abilities, ?DateTimeInterface $expiresAt = null, ?string $organizationId = null): NewAccessToken
    {
        $unknown = array_diff($abilities, ApiAbility::all());

        if ($abilities === [] || $unknown !== []) {
            throw new InvalidArgumentException('Unknown or missing abilities: '.implode(', ', $unknown));
        }

        if (! $user->canSignIn()) {
            throw new InvalidArgumentException('Tokens cannot be issued to inactive accounts.');
        }

        $organizationId ??= $user->current_organization_id;

        if ($organizationId === null || ! $user->organizations()->whereKey($organizationId)->where('is_active', true)->exists()) {
            throw new InvalidArgumentException('Tokens work in one of the user\'s organizations.');
        }

        $token = $user->createToken($name, $abilities, $expiresAt ?? now()->addMinutes(self::defaultLifetime()));
        $token->accessToken->forceFill(['organization_id' => $organizationId])->save();

        // Access to secrets and credentials is a security event (CLAUDE.md §6). Never log the token itself.
        $this->trail->record(AuditEvent::Updated, $user, null, ['api_token_issued' => ['name' => $name, 'abilities' => $abilities, 'organization' => $organizationId]]);

        return $token;
    }

    /**
     * Revokes a token of the user's, recorded like its issuing.
     */
    public function revoke(User $owner, string $tokenId, User $actor): bool
    {
        $token = $owner->tokens()->whereKey($tokenId)->first();

        if ($token === null) {
            return false;
        }

        $token->delete();
        $this->trail->record(AuditEvent::Updated, $owner, null, ['api_token_revoked' => ['name' => $token->getAttribute('name')]], $actor->id);

        return true;
    }

    private static function defaultLifetime(): int
    {
        $minutes = config('sanctum.expiration');

        return is_int($minutes) && $minutes > 0 ? $minutes : 60 * 24 * 90;
    }
}
