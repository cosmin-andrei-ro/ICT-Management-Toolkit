# ADR 0017 — Two-factor authentication and account self-service

- **Status:** Accepted
- **Date:** 2026-11-09

## Context

CLAUDE.md asks for MFA, NIST 800-63B passwords, sessions invalidated on password change, and ASVS L3 for authentication. Before this decision, the only local account was the administrator created at installation. There was no way to manage people, reset a password, or prove a second factor. The application holds sensitive IT information (and will hold risks, continuity plans and secrets).

## Decision

- **TOTP (RFC 6238)** with any standard authenticator app, through `pragmarx/google2fa` and `chillerlan/php-qrcode` (both MIT, already installed by Filament). WebAuthn is left for later (owner's decision); it can be added beside TOTP.
  - The secret is encrypted with the application key and hidden from serialization and the audit trail.
  - Codes are accepted ±1 time step, each **at most once**: the last accepted step is stored, and verification locks the user row.
  - Ten single-use recovery codes are stored as SHA-256 hashes and shown once.
  - Codes per account are limited to 5 per minute; failures are audited (`mfa_failed`).
- **Who (owner's decision):** required for everyone holding any role other than *Requester* in any organization, Active Directory accounts included; optional for the others. `RequireTwoFactor` (route middleware `mfa`, also persistent for Livewire updates and on the Filament panel) confines people who must use it and have not set it up to **Account security**.
- **Sign-in in two steps:** the password is checked without signing in. When a second factor is needed, the pending sign-in is kept server-side in the session for 5 minutes, after regenerating the session id; only a valid code signs the user in. The Login audit event is recorded only then.
- **Losing the phone:** a recovery code signs the person in, and a valid code (app or recovery) lets them turn two-factor off, which, for staff, leads straight back to the setup. Otherwise an administrator **resets** it (`mfa_reset`, audited with the administrator as actor), after checking who is asking.
- **Passwords (local accounts):**
  - Policy, once, through `Password::defaults()`: 12–128 characters, no composition rules (NIST 800-63B).
  - Changing or resetting a password rotates the remember token. With `auth.session` on every authenticated route, all other sessions end.
  - **Forgot password** answers the same for any address, is rate-limited, and only emails active local accounts. The email is queued, so response time reveals nothing. The link works for 60 minutes and once.
- **People management** (Administration → Access → People; `core.user.create` / `core.user.update` / `core.user.deactivate`, administrators by default):
  - **Inviting** creates a local account with an unusable password and emails a link valid for 3 days (`invites` password broker) to choose it. Administrators never know passwords. An existing account (another organization, or AD) is added without an email.
  - Roles of AD accounts stay owned by the group mappings.
  - Deactivation applies to the whole account, so it is offered only when the account belongs to the current organization alone; otherwise the person is removed from the organization.
  - Nobody changes their own roles, status or membership, which prevents self-lockout.
- **First administrator:** `core:setup` creates the first organization and its administrator, and is refused once any administrator exists.
- **Reverse proxies:** `TRUSTED_PROXIES` (addresses or CIDR). Forwarded headers from anyone else are ignored, so rate limits cannot be dodged with a forged address.

## Alternatives considered

- **Laravel Fortify:** covers much of this, but brings its own routes, views and flows to adapt to the module structure, the AD sign-in and the Livewire/CSP constraints. The pieces used here are small and tested.
- **Filament's built-in MFA:** protects only the admin panel, not the portal and desk.
- **Email or SMS codes:** weaker (mailbox takeover, SIM swapping), and SMS needs a provider.

## Consequences

- Staff must have a phone with an authenticator app before working in the application.
- Administrators handle resets for people without recovery codes; identity checks are a procedure outside the application.
- Password reset and invitation emails need the queue worker.
