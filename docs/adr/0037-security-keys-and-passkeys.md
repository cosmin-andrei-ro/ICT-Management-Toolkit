# ADR 0037 — Security keys and passkeys (WebAuthn)

- **Status:** Accepted
- **Date:** 2026-11-30

## Context

Two-factor authentication with authenticator-app codes (TOTP, ADR 0017) protects against stolen passwords, but not against phishing: a fake page can ask for the password and the current code and replay both at once. WebAuthn keys sign a challenge bound to the site's domain, so a key cannot be used by another site. CLAUDE.md §4 plans MFA with TOTP and WebAuthn; §6 targets ASVS level 3 for authentication; NIST SP 800-63B rates hardware keys at the highest assurance level (AAL3).

## Decision (owner's choices)

- **Security keys and passkeys as a second factor** in place of the code at sign-in, and **passkeys to sign in without a password**. The authenticator app and the recovery codes stay as the fallback: a key can only be added once the app is set up, and turning the app off (or an administrator's reset) removes the keys.
- **Library:** `web-auth/webauthn-lib` 5.3 (MIT, maintained, with its MIT dependencies: Symfony serializer, property-access and property-info, `spomky-labs/cbor-php`, `spomky-labs/pki-framework`, `web-auth/cose-lib`). The browser side is our own `resources/js/components/webauthn.js` (no npm package), registered with Alpine's CSP build; it only translates between base64url JSON and `navigator.credentials`. The Livewire components give the options and check the answers, so no new routes or CSRF handling are needed.
- **Settings:**
  - the relying party is the host of `APP_URL`, and the only allowed origin is `APP_URL`'s origin;
  - attestation is `none`: we do not identify the authenticator model (privacy, and no metadata service to run);
  - algorithms are ES256 and RS256;
  - registration prefers a discoverable credential ("passkey") and user verification;
  - a passkey sign-in requires user verification (PIN or biometrics), since it replaces both factors.
- **Storage:** `core_security_keys` holds the credential id and the credential record (public key, signature counter, flags) as the library serializes it. Nothing secret is stored. The counter is updated under a row lock.
- **Ceremonies:** each challenge lives in the session and is consumed when the answer arrives.
  - **Adding a key** requires a code from the authenticator app first, and must finish within 5 minutes.
  - **Removing a key** also requires a code.
  - **Second factor:** it is rate-limited like codes, and failures are audited as `mfa_failed`.
  - **Passkey sign-in:** it is rate-limited per address; failures are audited as `login_failed`, and inactive accounts are refused.
  - Additions and removals are audited (`security_key_added`, `security_key_removed`).

## Alternatives considered

- **`laragear/webauthn`** (MIT): integrates as a guard and user provider and ships its own JavaScript helper; our sign-in already has its own two-step flow and a strict CSP, so the lower-level library fits better.
- **Keys without the authenticator app:** simpler for users, but losing the only key would lock people out or require an administrator; recovery codes are tied to the app today.
- **Attestation with a metadata service** (to allow only certain authenticator models): useful in high-security settings; can be added later through the library's metadata support.

## Consequences

- Tests use a software authenticator (`Modules\Core\Tests\Support\VirtualAuthenticator`) that signs with a real P-256 key, so the full verification runs in the suite. The browser flow was checked with Chromium's virtual authenticator.
- `APP_URL` must be the address people use: keys made for one host do not work on another.
