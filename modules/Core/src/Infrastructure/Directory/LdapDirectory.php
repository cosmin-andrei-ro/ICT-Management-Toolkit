<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Directory;

use InvalidArgumentException;
use LdapRecord\Connection;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\Attributes\AccountControl;
use LdapRecord\Models\Attributes\Guid;
use LdapRecord\Query\Builder;
use Modules\Core\Domain\Directory\Directory;
use Modules\Core\Domain\Directory\DirectoryUnavailable;
use Modules\Core\Domain\Directory\DirectoryUser;

/**
 * Active Directory over LDAP. Lookups run as the configured service account;
 * passwords are verified by binding as the user, then rebinding as the service account.
 */
final readonly class LdapDirectory implements Directory
{
    /** LDAP_MATCHING_RULE_IN_CHAIN: matches nested group membership on the server. */
    private const string MEMBER_IN_CHAIN = 'member:1.2.840.113556.1.4.1941:';

    private const array USER_ATTRIBUTES = [
        'objectguid', 'distinguishedname', 'samaccountname', 'userprincipalname',
        'mail', 'displayname', 'cn', 'useraccountcontrol',
    ];

    public function __construct(
        private Connection $connection,
        private string $baseDn,
    ) {}

    public function isEnabled(): bool
    {
        return true;
    }

    public function findUser(string $identifier): ?DirectoryUser
    {
        $identifier = $this->normalizeIdentifier($identifier);

        if ($identifier === '') {
            return null;
        }

        $entries = $this->run(fn (): array => $this->query()
            ->where('objectclass', '=', 'user')
            ->where('objectcategory', '=', 'person')
            // orFilter, not where(closure): a nested where() group is always an AND,
            // whatever the inner methods are called.
            ->orFilter(function (Builder $query) use ($identifier): void {
                $query->where('samaccountname', '=', $identifier)
                    ->where('userprincipalname', '=', $identifier)
                    ->where('mail', '=', $identifier);
            })
            ->select(self::USER_ATTRIBUTES)
            // Two are enough to detect ambiguity, which is treated as "not found".
            ->limit(2)
            ->get());

        return count($entries) === 1 && is_array($entries[0]) ? $this->toDirectoryUser($entries[0]) : null;
    }

    public function findByGuid(string $guid): ?DirectoryUser
    {
        try {
            // Guid validates the format, so the raw (pre-escaped hex) filter value is safe.
            $encoded = (new Guid($guid))->getEncodedHex();
        } catch (InvalidArgumentException) {
            return null;
        }

        $entries = $this->run(fn (): array => $this->query()
            ->where('objectclass', '=', 'user')
            ->whereRaw('objectguid', '=', $encoded)
            ->select(self::USER_ATTRIBUTES)
            ->limit(2)
            ->get());

        return count($entries) === 1 && is_array($entries[0]) ? $this->toDirectoryUser($entries[0]) : null;
    }

    public function authenticate(DirectoryUser $user, string $password): bool
    {
        if ($password === '') {
            return false;
        }

        return $this->run(fn (): bool => $this->connection->auth()->attempt($user->distinguishedName, $password));
    }

    public function groupsOf(DirectoryUser $user): array
    {
        $entries = $this->run(fn (): array => $this->query()
            ->where('objectclass', '=', 'group')
            ->where(self::MEMBER_IN_CHAIN, '=', $user->distinguishedName)
            ->select(['distinguishedname'])
            ->get());

        $groups = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $dn = $this->first($entry, 'distinguishedname') ?? $this->first($entry, 'dn');

            if ($dn !== null) {
                $groups[] = mb_strtolower($dn);
            }
        }

        return array_values(array_unique($groups));
    }

    private function query(): Builder
    {
        return $this->connection->query()->in($this->baseDn);
    }

    /**
     * Strips a NetBIOS domain prefix (CORP\jdoe → jdoe).
     */
    private function normalizeIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        $backslash = strrpos($identifier, '\\');

        return $backslash === false ? $identifier : substr($identifier, $backslash + 1);
    }

    /**
     * @param  array<array-key, mixed>  $entry
     */
    private function toDirectoryUser(array $entry): ?DirectoryUser
    {
        $guid = $this->first($entry, 'objectguid');
        $dn = $this->first($entry, 'distinguishedname') ?? $this->first($entry, 'dn');
        $username = $this->first($entry, 'samaccountname');

        if ($guid === null || $dn === null || $username === null) {
            return null;
        }

        $email = $this->first($entry, 'mail') ?? $this->first($entry, 'userprincipalname');

        if ($email === null) {
            return null;
        }

        $control = new AccountControl((int) ($this->first($entry, 'useraccountcontrol') ?? 0));

        return new DirectoryUser(
            guid: (new Guid($guid))->getValue(),
            distinguishedName: $dn,
            username: $username,
            name: $this->first($entry, 'displayname') ?? $this->first($entry, 'cn') ?? $username,
            email: mb_strtolower(trim($email)),
            enabled: ! $control->hasFlag(AccountControl::ACCOUNTDISABLE),
        );
    }

    /**
     * @param  array<array-key, mixed>  $entry
     */
    private function first(array $entry, string $attribute): ?string
    {
        $value = $entry[$attribute] ?? null;

        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Connects lazily and turns transport failures into DirectoryUnavailable, so callers
     * can tell "directory down" apart from "wrong password".
     *
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     */
    private function run(callable $operation): mixed
    {
        try {
            if (! $this->connection->isConnected()) {
                $this->connection->connect();
            }

            return $operation();
        } catch (LdapRecordException $exception) {
            throw new DirectoryUnavailable('The directory could not be reached or rejected the service account.', previous: $exception);
        }
    }
}
