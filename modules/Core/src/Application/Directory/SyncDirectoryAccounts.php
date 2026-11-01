<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Directory;

use Modules\Core\Domain\Directory\AuthSource;
use Modules\Core\Domain\Directory\Directory;
use Modules\Core\Domain\Directory\DirectoryUnavailable;
use Modules\Core\Domain\Directory\DirectoryUser;
use Modules\Core\Domain\Models\User;

/**
 * Re-checks every active directory account against AD (ADR 0007), so that accounts
 * disabled, removed or moved between groups in AD lose access without signing in again.
 *
 * Reads everything first and changes nothing if AD is unreachable or the result looks
 * wrong (most accounts "missing" usually means a bad base DN, not a mass departure).
 */
final readonly class SyncDirectoryAccounts
{
    /** Refuse to apply when more than this share of accounts would be marked missing… */
    private const float MAX_MISSING_RATIO = 0.5;

    /** …unless there are this few accounts, where a ratio means little. */
    private const int MIN_ACCOUNTS_FOR_RATIO = 5;

    public function __construct(
        private Directory $directory,
        private SyncDirectoryUser $syncDirectoryUser,
    ) {}

    /**
     * @return array<value-of<DirectorySyncOutcome>, int>
     *
     * @throws DirectoryUnavailable when AD cannot be read; nothing is changed.
     * @throws DirectorySyncAborted when the result fails the safety check; nothing is changed.
     */
    public function handle(bool $force = false): array
    {
        $summary = array_fill_keys(array_map(fn (DirectorySyncOutcome $outcome): string => $outcome->value, DirectorySyncOutcome::cases()), 0);

        if (! $this->directory->isEnabled()) {
            return $summary;
        }

        /** @var list<array{user: User, entry: ?DirectoryUser, groups: list<string>}> $snapshot */
        $snapshot = [];

        // Phase 1: read only. Accounts already deactivated locally are left alone.
        User::query()
            ->where('auth_source', AuthSource::Ldap)
            ->where('is_active', true)
            ->orderBy('id')
            ->each(function (User $user) use (&$snapshot): void {
                $entry = $this->directory->findByGuid((string) $user->directory_guid);

                $snapshot[] = [
                    'user' => $user,
                    'entry' => $entry,
                    'groups' => $entry !== null && $entry->enabled ? $this->directory->groupsOf($entry) : [],
                ];
            });

        $missing = count(array_filter($snapshot, fn (array $item): bool => $item['entry'] === null));

        if (! $force && count($snapshot) >= self::MIN_ACCOUNTS_FOR_RATIO && $missing / count($snapshot) > self::MAX_MISSING_RATIO) {
            throw new DirectorySyncAborted($missing, count($snapshot));
        }

        // Phase 2: apply.
        foreach ($snapshot as $item) {
            $outcome = $this->syncDirectoryUser->refresh($item['user'], $item['entry'], $item['groups']);
            $summary[$outcome->value]++;
        }

        return $summary;
    }
}
