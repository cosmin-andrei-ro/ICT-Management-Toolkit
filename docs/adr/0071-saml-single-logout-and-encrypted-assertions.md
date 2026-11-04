# ADR 0071 — SAML single logout and encrypted assertions

- **Status:** Accepted
- **Date:** 2026-11-04
- **Amends:** [ADR 0039](0039-single-sign-on-with-saml.md)

## Context

ADR 0039 left out single logout and encrypted assertions. Without single logout, leaving a company's identity provider left a session open here, and signing out here left the provider's session open. Some organizations also require that assertions are unreadable to the browser passing them along.

## Decision

- **A key pair per provider,** made the first time it is needed (`SamlServiceProviderKeys`) under a row lock. The private key is encrypted with the application key, hidden from serialization and never in the metadata. The certificate is published in the metadata, for signature checks and encryption.
- **Encrypted assertions** are always readable, and can be required per provider. A provider that requires them refuses plain answers.
- **Signing out here** ends the session here first, so it never depends on the provider. If the provider has a single logout URL, the browser then goes there with a signed request. The response is a short page with a `Refresh` header and a link, not a redirect: `form-action 'self'` in the Content Security Policy also applies to redirects after a form.
- **Signing out at the provider:** a logout request counts only if the provider signed it, for the right destination, and is answered with a signed response. The answer is the same whether or not the person has an account.
- **It ends every session of that person, on all devices,** at their next request, including "keep me signed in". The sessions are not all listable in every store, so each session records when it signed in (`login.signed_in_at`), and `users.sessions_revoked_at` is compared on each request. The provider's answer to our own request changes nothing, because the session here has already ended.
- **HTTP-Redirect binding only,** which the library supports for logout.

## Alternatives considered

- **Ending only the session the provider names (SessionIndex):** more exact, but it needs a table of sessions and a way to reach them in Redis. Ending all is stricter, and the provider's logout means the person is leaving.
- **Keys shared by all providers:** simpler, but one leak would affect every organization.
- **Signing login requests as well:** some providers would then need the certificate before the first sign-in, and would otherwise refuse. Not needed for the goal.
- **Single logout for OpenID Connect:** another protocol with its own flows. Not asked for here.

## Consequences

- Tested in automated tests with a simulated provider, and by hand against one open-source identity provider: encrypted assertions, and logout started from either side. Other providers are untested.
- **Providers must not require signed login requests.** The toolkit signs logout messages only.
- **Encryption settings:** the XML security library decrypts the key only with RSA-OAEP using SHA-1 (`rsa-oaep-mgf1p`). A provider using SHA-256 there fails with a decryption error, so it must be set to this. The content itself may use AES-GCM or AES-CBC.
- **No key rotation yet.** The certificate is valid for 10 years. Replacing it means clearing it, which breaks the provider's trust until its metadata is imported again.
- Anyone who can get a signed request from the provider can only end sessions of that provider's own people.
