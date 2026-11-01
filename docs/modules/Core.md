# Core module

The foundation every other module builds on. Code: `modules/Core/`.

## Tenancy

- **Organizations** (`core_organizations`) are the tenant boundary. Users are global and belong to organizations through `core_organization_user`.
- `OrganizationContext` holds the organization of the current request or job. The `organization` route middleware sets it from the user's current organization, after checking active membership on every request.
- Models using `BelongsToOrganization` are filtered by a global scope. Without a context, they return no rows. Creating a record fills `organization_id` from the context, and creating it for another organization or moving it to one throws an exception.
- Queued jobs and commands must set the context explicitly: `OrganizationContext::runAs($organization, fn () => …)`.

## Audit trail (ADR 0005)

- Models using `Auditable` write field-level changes to `core_audit_logs`. Hidden attributes are never logged, and a model can exclude more with `auditExclude()`.
- The table is append-only, enforced by a database trigger.
- Authentication events (login, failed login, logout, lockout) are recorded too, as are role and permission changes.
- Each entry carries the IP address, user agent and request id of the HTTP request (`X-Request-Id`, echoed in the response).

## Authorization (ADR 0004)

- Permissions are named `<module>.<entity>.<action>`. Modules register them, and their grants to the default roles, in `PermissionRegistry` from their service provider.
- `php artisan core:permissions:sync` writes them to the database. Run it on every deployment.
- Checks go through Policies only.

## Authentication

- Login at `/login` (Livewire), logout via `POST /logout`.
- **Local accounts** sign in with their email. **Active Directory accounts** sign in with their username, `DOMAIN\username`, UPN or email (ADR 0007). Each account has a fixed source, with no fallback between them.
- AD accounts are created at first sign-in. Their organizations and roles come only from **AD group mappings** (admin panel → Access), and are re-synced on every sign-in.
- Throttling: 5 attempts per minute per identifier and IP address, and 20 attempts per 15 minutes per identifier.
- Inactive and unknown accounts get the same error. Accounts deactivated while signed in lose access on their next request.
- MFA, password reset and OIDC/SAML SSO are not implemented yet.

### Configuring Active Directory

1. Set the `LDAP_*` variables (see `.env.example`). Use LDAPS (port 636), or StartTLS (port 389) with `LDAP_ENCRYPTION=starttls`. Set `LDAP_CA_CERT` to your internal CA if the domain controllers' certificates are not publicly trusted.
2. Use a dedicated, read-only service account for `LDAP_USERNAME`/`LDAP_PASSWORD`.
3. Run `php artisan core:directory:test` to check the connection, then `php artisan core:directory:test jdoe` to see how a user would be mapped.
4. Map AD groups to roles in each organization (admin panel → Access → AD group mappings).

For development, an optional Samba AD container provides a test domain: see [docs/development/active-directory.md](../development/active-directory.md).

## Administration panel

Filament at `/admin`, for users with `core.admin.access`. It uses the application login. See ADR 0006 for its CSP exception.

## Security headers (ADR 0006)

A strict CSP with a per-request nonce, plus the standard hardening headers on every response.

## Permissions

| Permission | Purpose |
|---|---|
| `core.admin.access` | Open the administration panel |
| `core.organization.view` / `.update` | Organization settings |
| `core.user.view` / `.create` / `.update` / `.deactivate` | User management |
| `core.role.view` / `.manage` | Organization-specific roles |
| `core.department.view` / `.create` / `.update` / `.delete` | Departments |
| `core.audit.view` | Read the audit log |
| `core.directory.manage` | Manage AD group mappings |
