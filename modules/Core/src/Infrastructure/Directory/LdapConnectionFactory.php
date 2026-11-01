<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Directory;

use InvalidArgumentException;
use LdapRecord\Connection;
use LdapRecord\LdapInterface;

/**
 * Builds the LdapRecord connection from config('core.directory'), refusing
 * configurations that would send passwords in clear or skip certificate checks.
 */
final class LdapConnectionFactory
{
    /**
     * @param  array<string, mixed>  $config
     */
    public static function make(array $config, ?LdapInterface $ldap = null): Connection
    {
        $hosts = $config['hosts'] ?? [];
        $baseDn = $config['base_dn'] ?? '';
        $encryption = $config['encryption'] ?? 'ldaps';

        if (! is_array($hosts) || $hosts === [] || ! is_string($baseDn) || $baseDn === '') {
            throw new InvalidArgumentException('LDAP_HOSTS and LDAP_BASE_DN must be set when LDAP_ENABLED is true.');
        }

        if (! is_string($encryption) || ! in_array($encryption, ['ldaps', 'starttls', 'none'], true)) {
            throw new InvalidArgumentException('LDAP_ENCRYPTION must be ldaps, starttls or none.');
        }

        if ($encryption === 'none' && ($config['allow_insecure'] ?? false) !== true) {
            throw new InvalidArgumentException('Unencrypted LDAP sends passwords in clear text. Use ldaps or starttls, or set LDAP_ALLOW_INSECURE=true for local development only.');
        }

        $options = [LDAP_OPT_X_TLS_REQUIRE_CERT => LDAP_OPT_X_TLS_HARD];

        if (is_string($config['ca_cert'] ?? null) && $config['ca_cert'] !== '') {
            $options[LDAP_OPT_X_TLS_CACERTFILE] = $config['ca_cert'];
        }

        return new Connection([
            'hosts' => array_values(array_filter($hosts, 'is_string')),
            'port' => is_int($config['port'] ?? null) ? $config['port'] : 636,
            'base_dn' => $baseDn,
            'username' => is_string($config['username'] ?? null) ? $config['username'] : '',
            'password' => is_string($config['password'] ?? null) ? $config['password'] : '',
            'use_tls' => $encryption === 'ldaps',
            'use_starttls' => $encryption === 'starttls',
            'timeout' => is_int($config['timeout'] ?? null) ? $config['timeout'] : 5,
            'follow_referrals' => false,
            'options' => $options,
        ], $ldap);
    }
}
