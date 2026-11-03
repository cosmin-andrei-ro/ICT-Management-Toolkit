# ADR 0061 — API conventions and token management

- **Status:** Accepted
- **Date:** 2026-12-23

## Context

ADR 0009 started the REST API with tickets only. Tokens were issued from the command line by whoever runs the server, and each list endpoint picked its own parameters. The owner chose to extend the API to every module (CRUD for CMDB/ITAM, Risk/Audit and BIA/BCM/Applications, read-only for the rest), with a token screen, outbound webhooks and a monitoring endpoint (2026-11-03). Before adding dozens of endpoints, the shared rules must be fixed.

A flaw also showed up: a token worked in the user's *current* organization, the one last chosen in the browser. Switching organizations in the UI silently changed what an integration saw and changed.

## Decision

- **Tokens belong to an organization.**
  - `personal_access_tokens.organization_id`, set by `IssueApiToken`, defaults to the user's current organization. The user must be an active member.
  - `SetCurrentOrganization` (sanctum) uses the token's organization, and refuses the request (403) once the user is no longer a member there.
  - Tokens issued before this change keep the previous behaviour until they expire.
- **Self-service tokens:** `/account/api-tokens`, permission `core.api.use`, given to every IT role and not to requesters.
  - The person chooses a name, the abilities (registered by modules) and a validity of 1–365 days, and enters a **fresh authenticator code**. Issuing a credential is re-authenticated (CLAUDE.md §6); failures are rate limited and audited.
  - The token is shown once and never stored in clear.
- **Administration:** Administration → Access → API tokens (`core.api.manage`, administrators) lists the organization's tokens, with owner, abilities, last use and expiry, and revokes any of them. Revocation is audited, by its owner or by the administrator. The CLI command remains for operators.
- **List conventions** (`Modules\Core\Http\Api\ApiQuery`):
  - `filter[name]=value`: only the filters the endpoint declares, values up to 200 characters;
  - `sort=name,-name`: only the declared sorts;
  - `per_page` from 1 to 100, 25 by default;
  - Laravel pagination (`data`, `links`, `meta`) with the query string kept, and the key as a final tie-breaker so pages are stable.
  - Unknown filters, sorts or values answer **422** with the parameter named. A typo must not return the whole table.
  - Tickets moved to it. `status` and `search` from version 1 still work.
- **Errors** stay Laravel's JSON: `message`, plus `errors` keyed by field for 422. Status codes are 401 (no or invalid token), 403 (ability, permission or organization), 404, 422 and 429.
- **Abilities** follow `<area>:read` / `<area>:write`. Approvals (submit, approve, reject) are never exposed (owner's decision, 2026-11-03).

## Alternatives

- **JSON:API or a query-builder package:** rejected. A small helper covers what is needed without a new dependency.
- **Problem Details (RFC 9457) for errors:** postponed. Laravel's format is already documented by Scramble and used by the ticket API; changing it would break existing clients for little gain.
- **Re-authentication by password:** rejected. Single sign-on accounts have none, and IT staff must use two-factor authentication anyway.

## Consequences

- Every new list endpoint declares its filters and sorts through `ApiQuery`, and every new module registers its abilities.
- `docs/api/openapi.json` is regenerated with each API change.
