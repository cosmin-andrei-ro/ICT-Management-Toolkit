<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Sso;

use Illuminate\Contracts\Cache\Repository as Cache;
use Modules\Core\Domain\Sso\SsoProvider;
use OneLogin\Saml2\AuthnRequest;
use OneLogin\Saml2\Constants;
use OneLogin\Saml2\Response;
use OneLogin\Saml2\Settings;
use OneLogin\Saml2\Utils;
use Throwable;

/**
 * SAML 2.0 sign-in (ADR 0039), through onelogin/php-saml in strict mode:
 * signed assertions required, SHA-256 and up, schema validation, audience,
 * destination, time conditions. The identity provider answers with a
 * cross-site POST that carries no session cookie, so the request ID lives in
 * the cache instead: an answer is accepted once, and only to a request of
 * this provider.
 */
final readonly class Saml
{
    /** Seconds the provider has to send the person back. */
    private const int PENDING_SECONDS = 600;

    /** Authentication contexts that mean more than a password. */
    private const array MFA_CONTEXTS = [
        'http://schemas.microsoft.com/claims/multipleauthn',
        'https://refeds.org/profile/mfa',
        'urn:oasis:names:tc:SAML:2.0:ac:classes:MobileTwoFactorContract',
        'urn:oasis:names:tc:SAML:2.0:ac:classes:MobileTwoFactorUnregistered',
        'urn:oasis:names:tc:SAML:2.0:ac:classes:TimeSyncToken',
        'urn:oasis:names:tc:SAML:2.0:ac:classes:Smartcard',
        'urn:oasis:names:tc:SAML:2.0:ac:classes:SmartcardPKI',
    ];

    /** Where the email usually is when no attribute is configured. */
    private const array EMAIL_ATTRIBUTES = [
        'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress',
        'urn:oid:0.9.2342.19200300.100.1.3',
        'email',
        'mail',
    ];

    private const array NAME_ATTRIBUTES = [
        'http://schemas.microsoft.com/identity/claims/displayname',
        'urn:oid:2.16.840.1.113730.3.1.241',
        'displayName',
        'name',
    ];

    public function __construct(private Cache $cache) {}

    /**
     * Where to send the browser to sign in at the provider (HTTP-Redirect binding).
     *
     * @throws SsoFailed
     */
    public function loginUrl(SsoProvider $provider): string
    {
        try {
            $request = new AuthnRequest($this->settings($provider));
        } catch (Throwable) {
            throw SsoFailed::because('saml_settings');
        }

        $this->cache->put('sso.saml.request.'.$request->getId(), $provider->id, self::PENDING_SECONDS);
        $url = (string) $provider->sso_url;

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query(['SAMLRequest' => $request->getRequest()]);
    }

    /**
     * The claims of the person the provider vouches for, in the shape of an
     * OpenID Connect ID token (sub, email, name, groups, amr).
     *
     * @return array<string, mixed>
     *
     * @throws SsoFailed
     */
    public function claims(SsoProvider $provider, string $samlResponse): array
    {
        try {
            $response = new SamlResponse($this->settings($provider), $samlResponse);
        } catch (Throwable) {
            throw SsoFailed::because('saml_response');
        }

        $requestId = $response->getXMLDocument()->documentElement?->getAttribute('InResponseTo') ?? '';

        // Unsolicited answers, answers to another provider's request and replays are refused.
        if ($requestId === '' || $this->cache->pull('sso.saml.request.'.$requestId) !== $provider->id) {
            throw SsoFailed::because('saml_in_response_to');
        }

        if (! $this->validates($response, $requestId, $this->acsUrl($provider))) {
            throw SsoFailed::because('saml_invalid: '.$response->getError());
        }

        $attributes = [];

        foreach ($response->getAttributes() as $name => $values) {
            $attributes[(string) $name] = array_values(array_filter(is_array($values) ? $values : [], is_string(...)));
        }

        $nameId = $response->getNameId();

        return [
            'sub' => $nameId,
            'email' => $this->attribute($attributes, $provider->email_attribute, self::EMAIL_ATTRIBUTES) ?? (filter_var($nameId, FILTER_VALIDATE_EMAIL) !== false ? $nameId : null),
            'name' => $this->attribute($attributes, $provider->name_attribute, self::NAME_ATTRIBUTES),
            $provider->groups_claim => $attributes[$provider->groups_claim] ?? [],
            'amr' => array_intersect($response->authnContexts(), self::MFA_CONTEXTS) !== [] ? ['mfa'] : [],
        ];
    }

    /**
     * The toolkit's service provider metadata, to give the identity provider.
     *
     * @throws SsoFailed
     */
    public function metadata(SsoProvider $provider): string
    {
        try {
            return $this->settings($provider)->getSPMetadata();
        } catch (Throwable) {
            throw SsoFailed::because('saml_settings');
        }
    }

    public function entityId(SsoProvider $provider): string
    {
        return route('login.saml.metadata', $provider->id);
    }

    public function acsUrl(SsoProvider $provider): string
    {
        return route('login.saml.acs', $provider->id);
    }

    private function settings(SsoProvider $provider): Settings
    {
        return new Settings([
            'strict' => true,
            'debug' => false,
            'sp' => [
                'entityId' => $this->entityId($provider),
                'assertionConsumerService' => ['url' => $this->acsUrl($provider), 'binding' => Constants::BINDING_HTTP_POST],
                'NameIDFormat' => Constants::NAMEID_UNSPECIFIED,
            ],
            'idp' => [
                'entityId' => $provider->issuer,
                'singleSignOnService' => ['url' => (string) $provider->sso_url, 'binding' => Constants::BINDING_HTTP_REDIRECT],
                'x509cert' => (string) $provider->certificate,
            ],
            'security' => [
                'wantAssertionsSigned' => true,
                'wantMessagesSigned' => false,
                'wantNameId' => true,
                'wantXMLValidation' => true,
                'rejectUnsolicitedResponsesWithInResponseTo' => true,
                'rejectDeprecatedAlgorithm' => true,
                'destinationStrictlyMatches' => true,
                'requestedAuthnContext' => false,
                'signatureAlgorithm' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
                'digestAlgorithm' => 'http://www.w3.org/2001/04/xmlenc#sha256',
            ],
        ]);
    }

    /**
     * The library reads the current URL from the server globals; behind a
     * proxy or in a queued context they may be wrong, so they are set to the
     * consumer URL being validated, and put back afterwards.
     */
    private function validates(Response $response, string $requestId, string $acsUrl): bool
    {
        $parts = parse_url($acsUrl);
        $saved = ['REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? null, 'QUERY_STRING' => $_SERVER['QUERY_STRING'] ?? null];
        $_SERVER['REQUEST_URI'] = is_array($parts) ? ($parts['path'] ?? '/') : '/';
        unset($_SERVER['QUERY_STRING']);
        Utils::setBaseURL(is_array($parts) ? ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '').'/' : '');

        try {
            return $response->isValid($requestId);
        } catch (Throwable) {
            return false;
        } finally {
            Utils::setBaseURL('');

            foreach ($saved as $key => $value) {
                if ($value === null) {
                    unset($_SERVER[$key]);
                } else {
                    $_SERVER[$key] = $value;
                }
            }
        }
    }

    /**
     * @param  array<string, list<string>>  $attributes
     * @param  list<string>  $fallbacks
     */
    private function attribute(array $attributes, ?string $configured, array $fallbacks): ?string
    {
        foreach ($configured !== null && $configured !== '' ? [$configured] : $fallbacks as $name) {
            $value = $attributes[$name][0] ?? '';

            if (trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }
}
