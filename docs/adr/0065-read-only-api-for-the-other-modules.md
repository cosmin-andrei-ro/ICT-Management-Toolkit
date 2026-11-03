# ADR 0065 — Read-only API for the other modules

- **Status:** Accepted
- **Date:** 2026-12-28

## Context

The owner chose read-only API access for every module not covered by ADR 0062–0064 (2026-11-03). Typical uses are:

- reports and BI tools: indicators, projects, contracts;
- network and monitoring tools: racks, power, IP plan;
- an intranet showing help pages;
- integrations that resolve the people and team ids found in other responses.

## Decision

- **Abilities, one per area:**

  | Ability | What it reads |
  |---|---|
  | `directory:read` | people, teams, departments, locations |
  | `suppliers:read` | suppliers and contracts |
  | `dcim:read` | the data centre |
  | `documentation:read` | documentation |
  | `projects:read` | projects |
  | `metrics:read` | indicators |

  Each endpoint authorizes with the screen's policy or permission.
- **What is returned:**
  - **People:** id, name, e-mail and whether active, never anything else. Anonymized accounts are left out.
  - **Suppliers:** suppliers with their contacts; contracts with dates, renewal, notice, value and owner.
  - **Data centre:** racks with the equipment mounted, top to bottom (asset tag and name come through `PhysicalAssets`); power sources with their normal and worst-case load (`PowerGrid`); subnets with their next free address; IP addresses.
  - **Documentation:** the spaces the user may read, and **published** versions of their pages, the Markdown body on show. Drafts and pages awaiting approval never leave the screen.
  - **Projects:** all projects for portfolio readers, otherwise those the user sponsors, manages or works in; with phases, tasks, latest RAG report and budget figures.
  - **Indicators:** those the user's permissions show, with value, unit and status, and their daily history from the snapshots. An indicator the user may not see answers 404.
- **Shape:** lists follow `ApiQuery` (ADR 0061). Simple read-only lists map records to arrays through `ApiQuery::collection()`, which keeps the same `data` / `links` / `meta` shape without a resource class.

## Alternatives

- **One `read:all` ability:** rejected. Tokens should carry only what an integration needs (least privilege).
- **Exposing documentation drafts to editors:** rejected. Integrations publish what is approved.

## Consequences

- With ADR 0062–0064, every module is reachable through the API. Outbound webhooks and the monitoring alert endpoint come next.
