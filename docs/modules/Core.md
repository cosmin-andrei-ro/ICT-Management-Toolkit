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
- Shown in the application (ADR 0056): a **History** section on record pages (`<x-core::record-history :record :can-change />`, for those who may change the record or hold `core.audit.view`) and the **Audit log** page `/audit-log` (security events by default, filters, Excel export `core.audit_log`), scoped to the organization by `AuditLogQuery`.

## REST API (ADR 0009, 0061)

- Modules mount `routes/api.php` under `/api/v1` with `organization:sanctum`, `auth:sanctum`, `throttle:api` and `abilities:...` per route. Abilities are registered with `ApiAbility::register()`. Abilities narrow; policies decide.
- **Tokens:** `IssueApiToken` binds each token to an organization (`personal_access_tokens.organization_id`). `SetCurrentOrganization` uses that organization for the request; tokens issued before the binding follow the user's current organization. Issuing and revoking are audited.
  - `/account/api-tokens` (`core.api.use`, every IT role) lets people issue their own, after a fresh authenticator code. The token is shown once.
  - Administration → Access → API tokens (`core.api.manage`, administrators) lists and revokes every token of the organization.
  - `php artisan core:api-token` remains for operators.
- **Lists** go through `ApiQuery::paginate()`: declared `filter[name]` and `sort=a,-b` only, `per_page` 1–100 (default 25); anything else answers 422. Errors are Laravel's JSON (`message`, plus `errors` for 422).
- After changing the API, regenerate the description: `php artisan scramble:export --path=docs/api/openapi.json`.

## Personal data (ADR 0058)

- `PersonalData` (Core registry): each module registers a collector, `fn (User $person): iterable<PersonalDataEntry>` (record, reference, title, the person's part, date, personal content), under a translated sheet name. Core never reads module tables.
- `PersonalDataExport` (`core.personal_data`, permission `core.personal_data.manage`) builds the workbook in the export's organization:
  - an account sheet;
  - one sheet per module with entries;
  - comments and files the person wrote or uploaded;
  - their audit log activity, both what they did and what was done to their account.
  The person must be a member of that organization.
- Administration → Access → **Personal data** prepares it (queued, audited like every export) and lists the administrator's recent exports.
- New modules that store people in their records register a collector.

## Anonymization (ADR 0059)

- `AnonymizePerson` (Personal data page, `core.personal_data.manage`) needs the person's e-mail typed out and a fresh authenticator code from the administrator.
- It refuses:
  - the administrator themselves;
  - a person who also belongs to another organization;
  - the last administrator;
  - an account already anonymized.
- What it does:
  1. The modules' erasers run (`PersonalData::registerEraser`): Ticketing received e-mails, ITAM handover IP addresses, Reporting preferences and subscription.
  2. Sessions, API tokens, security keys, SSO identities, reset tokens, team memberships, roles and the membership end.
  3. The audit log is redacted through `core_redact_person_in_audit_log()`.
  4. The account becomes "Former user #…" with an `@anonymized.invalid` address, no credentials, and `users.anonymized_at` set.
  5. The `person_anonymized` event is recorded, without the old name.
- The audit trigger lets UPDATE through only while that function runs (`core.audit_redaction` set locally), and only for `old_values`, `new_values`, `ip_address` and `user_agent`.

## Data retention (ADR 0060)

- `DataRetention` (Core registry): modules register a policy. A policy has a key, a label, a help text, default years, and `fn (string $organizationId, CarbonImmutable $before): int`.
- `core_retention_settings.periods` stores years per policy for each organization. `null` keeps forever; a missing key takes the default. They are set under Administration → Access → Data retention (`core.personal_data.manage`).
- `core:retention` runs every night at 03:30 (`ApplyRetention`). It applies every policy in every organization and records `retention_applied` with the counts.
- Audit entries of no organization are purged after the longest audit period among organizations, and never while one keeps its log forever.
- Policies:
  - `core.audit_log` (5 years) deletes old entries through `core_purge_audit_log()`;
  - `ticketing.closed_tickets` (3 years) anonymizes closed tickets.
- The audit trigger lets a DELETE through only while that function runs (`core.audit_retention`). `AuditRedaction::removeFields()` (`core_redact_record_in_audit_log()`) takes a record's removed fields out of its entries.

## Authorization (ADR 0004)

- Permissions are named `<module>.<entity>.<action>`. Modules register them, and their grants to the default roles, in `PermissionRegistry` from their service provider.
- `php artisan core:permissions:sync` writes them to the database. Run it on every deployment.
- Checks go through Policies only.

## Authentication

- Login at `/login` (Livewire), logout via `POST /logout`.
- **Local accounts** sign in with their email. **Active Directory accounts** sign in with their username, `DOMAIN\username`, UPN or email (ADR 0007). Each account has a fixed source, with no fallback between them.
- AD accounts are created at first sign-in. Their organizations and roles come only from **AD group mappings** (admin panel → Access), and are re-synced on every sign-in.
- Throttling: 5 attempts per minute per identifier and IP address, and 20 attempts per 15 minutes per identifier.
- **Account lockout** ([ADR 0057](../adr/0057-account-lockout-breached-passwords-and-mfa-roles.md)): `AccountLockout` locks a local account after `AUTH_LOCKOUT_AFTER` (10) consecutive failed passwords or second factors (`users.failed_logins`, `users.locked_at`). It records `account_locked` and e-mails the person. A password reset or an administrator (People → Unlock) lifts it (`account_unlocked`).
- **Breached passwords:** `Password::defaults()` adds `uncompromised()` (Have I Been Pwned, k-anonymity) unless `AUTH_CHECK_BREACHED_PASSWORDS=false`. It fails open after `AUTH_BREACHED_PASSWORDS_TIMEOUT` seconds.
- **Who must use two-factor:** `core_security_settings.mfa_required_roles` per organization (Administration → Access → Security settings). Null means every role but Requester; administrators always must. `TwoFactorAuthentication::isRequiredFor()` applies each organization's choice to the roles held there.
- Inactive and unknown accounts get the same error. Accounts deactivated while signed in lose access on their next request.
- Two-factor authentication with an authenticator app and recovery codes ([ADR 0017](../adr/0017-account-security.md)); **security keys and passkeys** (WebAuthn, [ADR 0037](../adr/0037-security-keys-and-passkeys.md)) as the second factor, and passkeys to sign in from `/login` without a password. Keys live in `core_security_keys` (`SecurityKeys` service, `resources/js/components/webauthn.js`).
- **Single sign-on with OpenID Connect** ([ADR 0038](../adr/0038-single-sign-on-with-openid-connect.md)): providers per organization (`core_sso_providers`, Administration → Access → Single sign-on), group → role mappings (`core_sso_group_mappings`), linked identities (`core_sso_identities`), `/login/sso/{provider}` and its callback. `OpenIdConnect` runs the flow (PKCE, state, nonce, ID token verification), `SignInWithSso` links or creates the account, `OutboundUrlGuard` keeps provider URLs off internal addresses. **SAML 2.0** providers ([ADR 0039](../adr/0039-single-sign-on-with-saml.md)) live in the same table (`protocol`); `Saml` (onelogin/php-saml, strict) handles `/login/saml/{provider}`, the consumer `POST /login/saml/{provider}/acs` (exempt from CSRF; the request ID is kept in the cache and used once) and the public metadata `/saml/{provider}/metadata`.

### Configuring Active Directory

1. Set the `LDAP_*` variables (see `.env.example`). Use LDAPS (port 636), or StartTLS (port 389) with `LDAP_ENCRYPTION=starttls`. Set `LDAP_CA_CERT` to your internal CA if the domain controllers' certificates are not publicly trusted.
2. Use a dedicated, read-only service account for `LDAP_USERNAME`/`LDAP_PASSWORD`.
3. Run `php artisan core:directory:test` to check the connection, then `php artisan core:directory:test jdoe` to see how a user would be mapped.
4. Map AD groups to roles in each organization (admin panel → Access → AD group mappings).

5. Make sure the Laravel scheduler runs (`* * * * * php artisan schedule:run` in cron, or `php artisan schedule:work`). It runs `core:directory:sync` every 15 minutes (`LDAP_SYNC_SCHEDULE`). The sync blocks accounts disabled or removed in AD, and re-syncs roles after group changes. You can also run it by hand: `php artisan core:directory:sync`.

For development, an optional Samba AD container provides a test domain: see [docs/development/active-directory.md](../development/active-directory.md).

## Form schemas

`Modules\Core\Domain\Forms\FormSchema` defines small configurable forms (text, textarea, number, date, select, checkbox), stored as `jsonb`. It validates answers and stores them with the labels of the moment ([ADR 0011](../adr/0011-form-schemas.md)). It is used by the service catalog, and meant for custom fields in other modules.

## Business calendars

Working hours per weekday in a time zone, extra days off, and optionally Romanian public holidays (computed each year, including Orthodox Easter and Pentecost). An organization's default calendar drives working-time calculations such as SLA deadlines. Without one, Monday–Friday 09:00–17:00, Europe/Bucharest, with Romanian holidays applies. Managed in Administration → Organization → Business calendars (`core.calendar.manage`).

## Departments

The organization chart (`core_departments`), nested, with a manager: Administration → Organization → Departments. Business processes (BIA) belong to them.

## Locations, suppliers and contracts (ADR 0019)

- **Locations** (`core_locations`): nested sites, buildings, floors and rooms; `Location::paths()` gives "Site › Building › Room" labels for forms. Managed in Administration → Organization → Locations (`core.location.manage`).
- **Suppliers** (`core_suppliers`) and their **contacts** (`core_supplier_contacts`, business details only): `/suppliers`.
- **Contracts** (`core_contracts`): `/contracts`. The deadline is the end date minus the notice period; the status is derived from the dates (`Contract::statusOn()`, and `withStatus()` in SQL). Documents are Core attachments.
- **Reminders:** `core:contracts:remind`, daily at 07:00, emails the owner and the people with `core.contract.manage` at `CONTRACT_REMINDER_DAYS` (default 90,30) before each deadline, once each (`core_contract_reminders`), and renews contracts that renew automatically.

## Main menu

Modules add entries through `Modules\Core\Http\Navigation\Navigation::add()`: a link of its own (with an `order`), or an entry of a shared group (`SERVICE_DESK`, `CONFIGURATION`, `RESILIENCE`, `SUPPLIERS`) shown as a drop-down. A group with a single visible entry is shown as that link. The drop-downs are `<details>` elements, so they work without script; `resources/js/navigation.js` closes them on outside clicks and Escape.

## Administration panel

Filament at `/admin`, for users with `core.admin.access`: administrators, team leads, asset managers and continuity coordinators. Every resource and page checks its own permission, so each sees only what they may manage. It uses the application login. See ADR 0006 for its CSP exception.

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
| `core.personal_data.manage` | Export a person's personal data (administrators) |
| `core.directory.manage` | Manage AD group mappings |
| `core.team.view` / `.manage` | Teams |
| `core.calendar.manage` | Business calendars |
| `core.location.manage` | Locations |
| `core.supplier.view` / `.manage` | Suppliers and their contacts |
| `core.contract.view` / `.manage` | Contracts, their values and documents |
