# ADR 0007 — Active Directory sign-in over LDAPS, alongside local accounts

- **Status:** Accepted
- **Date:** 2026-11-01

## Context

Companies running the platform need their staff to sign in with their on-premises Active Directory accounts. Local accounts must keep working, at least for administrators and for installations without AD.

## Decision

### Authentication

- AD is reached over LDAP with `directorytree/ldaprecord` (MIT), behind the `Modules\Core\Domain\Directory\Directory` interface. We use only the core library; the sign-in flow is ours (`AttemptLogin`).
- **Each account has a fixed source** (`users.auth_source`: `local` or `ldap`), enforced by a database constraint. A local account always has a password and no directory GUID, while a directory account has a GUID and **no local password**. There is never a fallback from one source to the other.
- One login form accepts an email (local accounts), or a username, `DOMAIN\username`, UPN or email (AD). An identifier matching a local account is checked locally only. Any other identifier is looked up in AD when AD is enabled.
- The password is verified by binding as the user's DN. **Empty passwords are rejected** before any bind, because LDAP treats them as an unauthenticated bind, which succeeds.
- The connection must be encrypted (LDAPS or StartTLS), with certificate verification (`LDAP_OPT_X_TLS_HARD`, optional CA bundle). Plain LDAP requires `LDAP_ALLOW_INSECURE=true` and is meant for development only.
- Identifiers are escaped by the query builder. Ambiguous lookups (several matching accounts) count as "not found".
- Accounts disabled in AD (`userAccountControl`), and accounts deactivated or deleted locally, are denied.
- Directory accounts never get a "remember me" cookie: it would outlive a deactivation in AD.
- If AD is enabled but misconfigured or unreachable, directory users see "temporarily unavailable" and the error is reported. Local accounts keep working, so an administrator can still sign in and fix it.

### Provisioning and authorization

- The account is identified by `objectGUID`, which survives renames and OU moves, and is created at first sign-in.
- **AD group mappings** (`core_directory_group_mappings`: organization, group DN, role) are the only source of organization membership and roles for directory accounts. Nested groups count, through the `LDAP_MATCHING_RULE_IN_CHAIN` rule. On every sign-in, memberships and roles are re-synced. Only differences are applied, so an unchanged user produces no audit noise. Roles given manually to a directory account are removed.
- A user with no mapped group is denied. No account is created.
- An AD account whose email belongs to another local account is denied and logged (`login_denied`, `email_conflict`). Linking automatically would let anyone controlling an AD account with that email take over the local one.
- A mapping can only use a global role or a role of its own organization. Managing mappings requires `core.directory.manage`, and every change is audited.

### Configuration

- Connection settings and the service account are environment variables (`LDAP_*`), so the secret never reaches the database.
- Mappings are edited in the admin panel ("Access → AD group mappings").
- `php artisan core:directory:test [user]` checks the connection and shows how a user would be mapped.

## Rejected alternatives

- **LdapRecord-Laravel's authentication provider:** its import and fallback model does not match "fixed source per account" and organization-scoped role mapping without overriding most of it.
- **Linking AD accounts to existing local accounts by email:** an account-takeover risk.
- **Storing the service account in the database:** a secret at rest, reachable by anyone with database access.

## Consequences

- A user disabled in AD keeps an existing session until it expires. A scheduled `core:directory:sync` to deactivate such accounts between sign-ins is planned as a follow-up.
- Tests use an in-memory directory for the flow, and LdapRecord's `LdapFake` for the LDAP implementation (filters, escaping, binds). Running against a real domain controller remains a manual step before production.
- Entra ID (Azure AD) through OIDC is not covered. It would be a separate source (`oidc`) under the same rules.
