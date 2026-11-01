<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use LdapRecord\Models\Attributes\Guid;
use LdapRecord\Testing\LdapExpectation;
use LdapRecord\Testing\LdapFake;
use Modules\Core\Domain\Directory\DirectoryUnavailable;
use Modules\Core\Domain\Directory\DirectoryUser;
use Modules\Core\Infrastructure\Directory\LdapConnectionFactory;
use Modules\Core\Infrastructure\Directory\LdapDirectory;
use PHPUnit\Framework\Assert as PHPUnit;

const BASE_DN = 'dc=corp,dc=example';
const SERVICE_DN = 'cn=svc-ictmt,ou=service,dc=corp,dc=example';
const JANE_GUID = '2a7b1c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d';

function ldapConfig(array $overrides = []): array
{
    return [
        'hosts' => ['dc1.corp.example'],
        'port' => 636,
        'base_dn' => BASE_DN,
        'username' => SERVICE_DN,
        'password' => 'service-secret',
        'encryption' => 'ldaps',
        'allow_insecure' => false,
        'ca_cert' => null,
        'timeout' => 5,
        ...$overrides,
    ];
}

/**
 * @param  array<string, mixed>  $attributes
 * @return array<string|int, mixed> An entry in ldap_get_entries() format.
 */
function ldapEntry(string $dn, array $attributes): array
{
    $entry = ['dn' => $dn, 'count' => count($attributes)];

    foreach ($attributes as $name => $value) {
        $entry[$name] = ['count' => 1, 0 => $value];
    }

    return $entry;
}

function janeEntry(int $userAccountControl = 512): array
{
    return ldapEntry('CN=Jane Doe,OU=Staff,DC=corp,DC=example', [
        'objectguid' => (new Guid(JANE_GUID))->getBinary(),
        'distinguishedname' => 'CN=Jane Doe,OU=Staff,DC=corp,DC=example',
        'samaccountname' => 'jdoe',
        'userprincipalname' => 'jdoe@corp.example',
        'mail' => 'Jane.Doe@Corp.Example',
        'displayname' => 'Jane Doe',
        'useraccountcontrol' => (string) $userAccountControl,
    ]);
}

/**
 * Binds happen in order: service account (connect), the user, service account again.
 *
 * @return list<LdapExpectation>
 */
function bindSequence(LdapExpectation $userBind): array
{
    return [
        LdapFake::operation('bind')->with(SERVICE_DN, 'service-secret')->once()->andReturnResponse(),
        $userBind,
        LdapFake::operation('bind')->with(SERVICE_DN, 'service-secret')->once()->andReturnResponse(),
    ];
}

function directoryWith(LdapFake $ldap): LdapDirectory
{
    return new LdapDirectory(LdapConnectionFactory::make(ldapConfig(), $ldap), BASE_DN);
}

describe('finding users', function (): void {
    it('maps an AD account, converting the binary objectGUID', function (): void {
        $ldap = (new LdapFake)->shouldAllowAnyBind()->expect([
            LdapFake::operation('search')->andReturn(['count' => 1, 0 => janeEntry()]),
        ]);

        $user = directoryWith($ldap)->findUser('jdoe');

        expect($user)->toEqual(new DirectoryUser(
            guid: JANE_GUID,
            distinguishedName: 'CN=Jane Doe,OU=Staff,DC=corp,DC=example',
            username: 'jdoe',
            name: 'Jane Doe',
            email: 'jane.doe@corp.example',
            enabled: true,
        ));
    });

    it('reads the ACCOUNTDISABLE flag', function (): void {
        $ldap = (new LdapFake)->shouldAllowAnyBind()->expect([
            LdapFake::operation('search')->andReturn(['count' => 1, 0 => janeEntry(514)]),
        ]);

        expect(directoryWith($ldap)->findUser('jdoe')?->enabled)->toBeFalse();
    });

    it('escapes the identifier in the LDAP filter and strips the domain prefix', function (): void {
        $captured = null;
        $ldap = (new LdapFake)->shouldAllowAnyBind()->expect([
            LdapFake::operation('search')
                ->with(BASE_DN, PHPUnit::callback(function (string $filter) use (&$captured): bool {
                    $captured = $filter;

                    return true;
                }), PHPUnit::anything())
                ->andReturn(['count' => 0]),
        ]);

        directoryWith($ldap)->findUser('CORP\\*)(objectclass=*');

        $escaped = '\\2a\\29\\28\\6f\\62\\6a\\65\\63\\74\\63\\6c\\61\\73\\73\\3d\\2a';

        // Any of the three attributes may match, and the injected filter stays a literal value.
        expect($captured)
            ->toContain("(|(samaccountname={$escaped})(userprincipalname={$escaped})(mail={$escaped}))")
            ->not->toContain('*)(objectclass=*')
            ->not->toContain('corp\\');
    });

    it('treats ambiguous matches as not found', function (): void {
        $ldap = (new LdapFake)->shouldAllowAnyBind()->expect([
            LdapFake::operation('search')->andReturn(['count' => 2, 0 => janeEntry(), 1 => janeEntry()]),
        ]);

        expect(directoryWith($ldap)->findUser('jdoe'))->toBeNull();
    });

    it('reports a failing service account bind as unavailable', function (): void {
        $ldap = (new LdapFake)->expect([
            LdapFake::operation('bind')->with(SERVICE_DN, PHPUnit::anything())->andReturnErrorResponse(49, 'Invalid credentials'),
        ]);

        directoryWith($ldap)->findUser('jdoe');
    })->throws(DirectoryUnavailable::class);
});

describe('authentication', function (): void {
    beforeEach(function (): void {
        $this->jane = new DirectoryUser(JANE_GUID, 'CN=Jane Doe,OU=Staff,DC=corp,DC=example', 'jdoe', 'Jane Doe', 'jane.doe@corp.example', true);
    });

    it('binds as the user with the given password', function (): void {
        $ldap = (new LdapFake)->expect(bindSequence(LdapFake::operation('bind')->with('CN=Jane Doe,OU=Staff,DC=corp,DC=example', 'ad-secret')->once()->andReturnResponse()));

        expect(directoryWith($ldap)->authenticate($this->jane, 'ad-secret'))->toBeTrue();
    });

    it('returns false on invalid credentials', function (): void {
        $ldap = (new LdapFake)->expect(bindSequence(LdapFake::operation('bind')->with('CN=Jane Doe,OU=Staff,DC=corp,DC=example', 'wrong')->once()->andReturnErrorResponse(49, 'Invalid credentials')));

        expect(directoryWith($ldap)->authenticate($this->jane, 'wrong'))->toBeFalse();
    });

    it('never binds with an empty password', function (): void {
        // No bind expectation: any bind attempt would fail the test.
        expect(directoryWith(new LdapFake)->authenticate($this->jane, ''))->toBeFalse();
    });
});

describe('groups', function (): void {
    it('queries nested membership with the in-chain matching rule and lowercases DNs', function (): void {
        $captured = null;
        $ldap = (new LdapFake)->shouldAllowAnyBind()->expect([
            LdapFake::operation('search')
                ->with(BASE_DN, PHPUnit::callback(function (string $filter) use (&$captured): bool {
                    $captured = $filter;

                    return true;
                }), PHPUnit::anything())
                ->andReturn([
                    'count' => 2,
                    0 => ldapEntry('CN=IT-ServiceDesk,OU=Groups,DC=corp,DC=example', ['distinguishedname' => 'CN=IT-ServiceDesk,OU=Groups,DC=corp,DC=example']),
                    1 => ldapEntry('CN=All-Staff,OU=Groups,DC=corp,DC=example', ['distinguishedname' => 'CN=All-Staff,OU=Groups,DC=corp,DC=example']),
                ]),
        ]);

        $jane = new DirectoryUser(JANE_GUID, 'CN=Jane Doe,OU=Staff,DC=corp,DC=example', 'jdoe', 'Jane Doe', 'jane.doe@corp.example', true);

        expect(directoryWith($ldap)->groupsOf($jane))->toBe([
            'cn=it-servicedesk,ou=groups,dc=corp,dc=example',
            'cn=all-staff,ou=groups,dc=corp,dc=example',
        ])->and($captured)->toContain('member:1.2.840.113556.1.4.1941:=');
    });
});

describe('connection configuration', function (): void {
    it('refuses unencrypted LDAP unless explicitly allowed', function (): void {
        LdapConnectionFactory::make(ldapConfig(['encryption' => 'none']));
    })->throws(InvalidArgumentException::class, 'clear text');

    it('allows unencrypted LDAP only with the explicit flag', function (): void {
        expect(LdapConnectionFactory::make(ldapConfig(['encryption' => 'none', 'allow_insecure' => true])))->not->toBeNull();
    });

    it('requires hosts and a base DN', function (array $overrides): void {
        LdapConnectionFactory::make(ldapConfig($overrides));
    })->with([[['hosts' => []]], [['base_dn' => '']]])->throws(InvalidArgumentException::class);

    it('rejects unknown encryption modes', function (): void {
        LdapConnectionFactory::make(ldapConfig(['encryption' => 'ssl']));
    })->throws(InvalidArgumentException::class);

    it('requires certificate verification and uses LDAPS by default', function (): void {
        $config = LdapConnectionFactory::make(ldapConfig(['ca_cert' => '/etc/ssl/corp-ca.pem']))->getConfiguration();

        expect($config->get('use_tls'))->toBeTrue()
            ->and($config->get('use_starttls'))->toBeFalse()
            ->and($config->get('options'))->toMatchArray([
                LDAP_OPT_X_TLS_REQUIRE_CERT => LDAP_OPT_X_TLS_HARD,
                LDAP_OPT_X_TLS_CACERTFILE => '/etc/ssl/corp-ca.pem',
            ]);
    });
});
