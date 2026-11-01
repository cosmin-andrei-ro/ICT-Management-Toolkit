<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Tests\Support;

use Modules\Core\Domain\Directory\Directory;
use Modules\Core\Domain\Directory\DirectoryUnavailable;
use Modules\Core\Domain\Directory\DirectoryUser;

/**
 * In-memory directory for tests; mirrors the contract's rules (empty passwords
 * never authenticate, ambiguous lookups find nothing).
 */
final class FakeDirectory implements Directory
{
    /** @var array<string, array{user: DirectoryUser, password: string, groups: list<string>}> */
    private array $accounts = [];

    private bool $unavailable = false;

    /**
     * @param  list<string>  $groups
     */
    public function addUser(DirectoryUser $user, string $password, array $groups = []): self
    {
        $this->accounts[$user->guid] = [
            'user' => $user,
            'password' => $password,
            'groups' => array_map('mb_strtolower', $groups),
        ];

        return $this;
    }

    public function makeUnavailable(): self
    {
        $this->unavailable = true;

        return $this;
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function findUser(string $identifier): ?DirectoryUser
    {
        $this->failIfUnavailable();

        $identifier = mb_strtolower(trim($identifier));
        $identifier = str_contains($identifier, '\\') ? substr($identifier, strrpos($identifier, '\\') + 1) : $identifier;

        $matches = array_values(array_filter(
            $this->accounts,
            fn (array $account): bool => in_array($identifier, [mb_strtolower($account['user']->username), $account['user']->email], true),
        ));

        return count($matches) === 1 ? $matches[0]['user'] : null;
    }

    public function authenticate(DirectoryUser $user, string $password): bool
    {
        $this->failIfUnavailable();

        return $password !== '' && ($this->accounts[$user->guid]['password'] ?? null) === $password;
    }

    public function groupsOf(DirectoryUser $user): array
    {
        $this->failIfUnavailable();

        return $this->accounts[$user->guid]['groups'] ?? [];
    }

    private function failIfUnavailable(): void
    {
        if ($this->unavailable) {
            throw new DirectoryUnavailable('Fake directory is down.');
        }
    }
}
