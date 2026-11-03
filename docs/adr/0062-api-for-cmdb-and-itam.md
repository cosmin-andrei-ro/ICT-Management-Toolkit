# ADR 0062 — API for the CMDB and ITAM

- **Status:** Accepted
- **Date:** 2026-12-24

## Context

The owner chose to open the CMDB and ITAM to integrations first: discovery tools that create and update configuration items, monitoring that reads dependencies, and procurement or HR systems that register equipment and hand it out. The conventions are those of ADR 0061.

## Decision

- **Abilities:** `cmdb:read`, `cmdb:write`, `assets:read`, `assets:write`. Permissions and policies are exactly the screens', and organization scoping comes from the token (ADR 0061). A record of another organization answers 404.
- **Same actions as the screens.** Controllers validate the shape (FormRequest) and call `SaveConfigurationItem`, `ManageRelationships`, `SaveAsset`, `AssignAsset`, `ChangeAssetStatus`, `SaveLicense` and `ManageLicenseSeats`, so audit trail, history, notifications and business rules are identical.
  - Domain refusals become 422 on the relevant field: an invalid transition, a person of another organization, seats in use.
  - Errors of type attributes are returned under `properties.<key>`.
- **POST creates, PATCH changes only what is sent.** `properties` (the attributes of the item's type or asset's category) are merged by key, unless the type or category changes. Then only the values sent count, as on screen.
- **Status changes have their own endpoints.** An asset's status is never a field to PATCH.
  - `assign` hands it out, opening an assignment.
  - `status` returns it to stock (closing the assignment), sends it to or back from repair, retires it or records it lost.

  Disposal (with the data-wipe record) and licence key reveal stay on screen.
- **Secrets stay write-only.** A licence key can be set or removed through the API but is never returned; `has_key` says whether one is stored. Revealing it needs a fresh second factor on screen (ADR 0022 and CLAUDE.md §6).
- **Configuration item deletion** is the soft delete of the screens.

## Alternatives

- **PUT with full replacement:** rejected. Discovery tools know only some fields, and a full PUT would blank the rest.
- **Status as a writable field:** rejected. It would bypass the assignment history and the hand-over receipt.

## Consequences

- Endpoints for Risk/Audit and BIA/BCM/Applications follow the same pattern. Approvals are never exposed (ADR 0061).
- `docs/api/openapi.json` now describes the CMDB and ITAM endpoints.
