# ADR 0039 — Single sign-on with SAML 2.0

- **Status:** Accepted
- **Date:** 2026-12-02

## Context

OpenID Connect sign-in (ADR 0038) covers modern identity providers. Many companies still run ADFS, Shibboleth or older identity providers that only speak SAML 2.0, and some prefer SAML for Entra ID enterprise applications. The owner chose OIDC first and SAML2 after, with the same account and role model.

## Decision

- **SAML providers live next to OIDC ones** in `core_sso_providers`, with a `protocol` column:
  - for SAML, `issuer` is the IdP entity ID, and the provider adds a single sign-on URL (HTTP-Redirect binding) and the IdP's signing certificate (public, not a secret);
  - optional email and name attributes, with Microsoft, LDAP OID and plain fallbacks, or the NameID when it is an email;
  - the groups attribute.
  
  A CHECK constraint keeps each protocol's required fields.
- **Library:** `onelogin/php-saml` 4.3 (MIT; it brings `robrichards/xmlseclibs`, BSD-3-Clause, compatible with AGPLv3), used in **strict mode**:
  - signed assertions required;
  - deprecated algorithms (SHA-1) rejected;
  - XML schema validation;
  - audience, destination (strict), issuer, subject confirmation and time conditions.
- **The toolkit as service provider:**
  - its entity ID is its metadata URL, `/saml/{provider}/metadata`, which is public so it can be given to the IdP;
  - its assertion consumer service is `POST /login/saml/{provider}/acs`;
  - requests are not signed, and no SP certificate is needed.
- **Cross-site POST:** the IdP posts its answer from its own site.
  - The consumer is exempt from CSRF, because the signed assertion stands in for the token.
  - The session cookie (SameSite=Lax) is not sent with that POST, so the AuthnRequest ID is kept in the **cache** for 10 minutes and **consumed** by the answer. Unsolicited answers, answers to another provider's request and replays are refused.
  - SameSite is not relaxed for the rest of the application.
- **Current URL:** the library reads it from server globals. They are set to the consumer URL while the answer is validated, then put back, so validation behaves the same behind a proxy and in tests.
- **Mapping to the account model:** the assertion becomes the same claims as an OIDC ID token, and `SignInWithSso` applies the same rules (ADR 0038):
  - **sub** is the NameID;
  - **email**, **name** and **groups** come from the attributes;
  - **amr** is `mfa` when the AuthnContextClassRef is a multi-factor context (Microsoft *multipleauthn*, REFEDS MFA, SAML two-factor and smartcard classes). It is read from the **signed assertion** only (`SamlResponse`): the response around it is not signed, and an `AuthnStatement` slipped into `samlp:Extensions` must not pass for a second factor (found in the security review of 2026-11-03).

## Alternatives considered

- **`24slides/laravel-saml2`** and similar wrappers: thin layers over the same library, with their own routes, events and tenant tables to fit around ours.
- **`simplesamlphp`** as a separate service provider: a whole application to deploy and keep up to date.
- **Session-held request ID with SameSite=None:** would make every session cookie cross-site, weakening CSRF protection elsewhere.

## Consequences

- Tests sign assertions with a certificate made on the spot (`FakeSamlIdentityProvider`) and cover forged, unsigned, re-signed, replayed, unsolicited, mis-addressed and expired answers.
- Single logout (SLO) is not implemented: signing out of the toolkit does not sign out of the identity provider, and the reverse.
- Encrypted assertions are not supported yet (they need an SP key pair).
