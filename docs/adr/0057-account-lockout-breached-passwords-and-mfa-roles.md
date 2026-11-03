# ADR 0057 — Account lockout, breached passwords and roles that must use two-factor

- **Status:** Accepted
- **Date:** 2026-12-19
- **Amended by:** [ADR 0069](0069-password-length-and-composition.md) (password length and composition)

## Context

CLAUDE.md §6 asks for blocking after failed attempts, a password policy following NIST SP 800-63B, and MFA. Sign-ins were rate limited (ADR 0007, 0017), but an account was never locked, passwords known from breaches were accepted, and the rule "two-factor for every role but Requester" was fixed in code. The owner chose (2026-11-03):

- a lock that an administrator lifts;
- a breach check on by default, which installations without internet access can turn off;
- a choice, per organization, of the roles that must use two-factor authentication.

## Decision

- **Lockout** (`AccountLockout`):
  - Consecutive failures of a **local** account count: wrong passwords and wrong second factors (code or security key) alike, so knowing the password does not allow unlimited code guessing.
  - Only a completed sign-in resets the counter.
  - At `AUTH_LOCKOUT_AFTER` failures (default 10, 0 turns locking off), `users.locked_at` is set. The `account_locked` event is recorded and the person is e-mailed (`AccountLocked`), so a lock caused by someone else is noticed.
  - While locked, every sign-in method fails with the generic message: password, code, security key, passkey, single sign-on. A locked account therefore cannot be told apart from a wrong password.
  - A password reset lifts the lock, because the link proves control of the mailbox. An administrator can lift it too (People → Unlock, `manageAccess`). Both record `account_unlocked`.
  - Active Directory accounts are left to the directory's own lockout policy.
  - The counter is written with plain queries and left out of the record history: the lock and the unlock carry their own events.
- **Breached passwords:**
  - `Password::defaults()` adds `uncompromised()` when `AUTH_CHECK_BREACHED_PASSWORDS` is true (the default). The check uses the Have I Been Pwned range API with k-anonymity: only the first five hex characters of the password's SHA-1 leave the server.
  - The verifier is extended, not rebound, because the validation provider is deferred. It gets a short timeout (`AUTH_BREACHED_PASSWORDS_TIMEOUT`, 5 s) and fails open: when the service does not answer, the password is accepted rather than blocking people.
  - Tests turn the check off in `phpunit.xml` and fake the API where they exercise it.
- **Roles that must use two-factor** (`core_security_settings`, one row per organization, audited):
  - `mfa_required_roles` is a list of role names. Null, the default, keeps the previous rule: every role but Requester, the organization's own roles included.
  - Administrators always must.
  - `TwoFactorAuthentication::isRequiredFor()` applies, for each role a person holds, the choice of the organization they hold it in.
  - The setting lives on Administration → Access → Security settings (`core.role.manage`). That page also shows the two installation-wide values.

## Alternatives

- **Time-limited lockout instead of a lock lifted by a person:** rejected by the owner. The existing per-account rate limit (20 per 15 minutes) already acts as a temporary lockout.
- **Lockout and breach check as organization settings:** rejected. Accounts are shared across organizations, so these apply to the account, not to one organization.
- **Failing closed when the breach service is unreachable:** rejected. An outage of a third-party service would block every password change and reset.

## Consequences

- Someone who knows an e-mail address can lock that account (denial of service). The e-mail and the self-service reset keep the cost low, and the rate limits slow the attempts down.
- New sign-in methods must call `AccountLockout::recordFailure()` on failure and go through `CompleteLogin::signIn()`, which refuses locked accounts.
- Installations without internet access set `AUTH_CHECK_BREACHED_PASSWORDS=false`. Otherwise each password change waits up to the timeout.
