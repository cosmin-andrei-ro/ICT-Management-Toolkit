# ADR 0038 — Single sign-on with OpenID Connect

- **Status:** Accepted
- **Date:** 2026-12-01

## Context

Most companies already keep identities in a cloud or on-premises identity provider. Signing in there means one password, the company's own second factor and conditional access, and access that ends when the person leaves. CLAUDE.md §4 plans SSO through OIDC and SAML2. The toolkit hosts several organizations (legal entities) of one company in one installation; organization administrators must not be able to reach other organizations' accounts.

## Decision (owner's choices)

- **OIDC first**, SAML2 in a later change, with the same account and role model.
- **Providers belong to an organization** (`core_sso_providers`) and are set up by its administrators in Administration → Access → Single sign-on, with the same permission as the AD group mappings (`core.directory.manage`). Each provider has:
  - issuer, client id, client secret (encrypted with the application key, hidden, never audited, write-only in the form), scopes;
  - the **email domains** it may sign in (required);
  - the claim carrying the person's groups;
  - whether its own second factor is trusted;
  - enabled or not.
- **Flow:**
  - Authorization code with **PKCE (S256)**, `state` and `nonce` kept in the session for 10 minutes, the code exchanged with `client_secret_post`.
  - The **ID token is verified** with `web-token/jwt-library` (MIT): signature against the provider's JWKS, issuer, audience (and `azp` with several audiences), expiry, issue time and nonce, with 60 seconds of leeway.
  - Only asymmetric algorithms are accepted (RS256/384/512, PS256, ES256/384): never `none`, never HMAC keyed with a public value.
  - The discovery document and the keys are cached for an hour; when no cached key matches, the key set is fetched again once (key rotation).
- **SSRF:** issuer, token and key URLs must be HTTPS, plain HTTP only with `SSO_ALLOW_HTTP` for a development provider. They must not resolve to loopback, link-local (cloud metadata) or reserved addresses, and redirects are not followed. Private networks stay allowed for on-premises providers.
- **Accounts:**
  1. Signed in by the provider's stable identifier (`sub`), kept in `core_sso_identities`.
  2. At first sign-in, an **existing account with the same email** is linked only when it belongs to the provider's organization alone; otherwise sign-in is refused. This stops an administrator from taking over accounts of other organizations by running their own provider.
  3. Otherwise an account is **created** (`auth_source = sso`, no password) in the provider's organization.
  4. Emails outside the allowed domains, or marked unverified, are refused.
- **Roles:** for accounts created this way, roles come from the provider's **group mappings** (`core_sso_group_mappings`) in its organization at every sign-in, and are *Requester* when no group matches. Linked local and AD accounts keep their own roles.
- **Second factor:** when the provider is trusted and its token says a second factor was used (`amr` with `mfa`, or two distinct factors from RFC 8176), the session counts as two-factor. The toolkit then does not ask for its code or its setup for that session. Otherwise people who use the toolkit's two-factor authentication give it, as after a password.
- Refusals are audited (`login_denied`, with the provider and the reason) and logged; the person sees a general message. Sign-ins are audited as usual.

## Alternatives considered

- **Laravel Socialite** (MIT): built for social logins, with no generic OIDC provider and no ID token verification of its own; community providers would add more packages per identity provider.
- **`jumbojett/openid-connect-php`** (Apache-2.0): a complete client, but with its own session and HTTP handling; ours is a small flow over Laravel's HTTP client and session with the critical checks in one class, testable with faked responses.
- **Providers in environment variables:** simpler, but one provider per installation and no per-organization setup.
- **Linking existing accounts by email without restriction:** convenient, but unsafe across organizations (see above).

## Consequences

- Tests use a fake provider signing with a real RSA key (`FakeIdentityProvider`); the tests cover forged, expired, mis-addressed, replayed and HMAC-signed tokens.
- Providers that do not send `amr` leave the toolkit's own second factor in place unless their administrators configure the claim.
- An account used by several organizations cannot be linked automatically: its owner keeps signing in as before.
