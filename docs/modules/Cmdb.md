# CMDB module

Configuration items, their relationships and the impact of failures. Code: `modules/Cmdb/`. Decisions: [ADR 0018](../adr/0018-cmdb.md).

## Concepts

- **Item type** (`cmdb_ci_types`): a kind of item with its attributes (Core form schema). Eight defaults per organization, created on first use: business service, application, physical server, virtual machine, database, network device, storage, cloud service.
- **Configuration item** (`cmdb_configuration_items`): name, optional identifier (unique per organization, case-insensitive), status (planned, in use, maintenance, retired), environment, criticality, owner, support team, description and attribute values (`properties`).
- **Recovery capability** of an item: `recovery_time_minutes` and `recovery_point_minutes`, what IT can deliver if it fails; compared with the BIA's requirements ([ADR 0024](../adr/0024-bia-dependencies-and-recovery-gaps.md)).
- **Relationship** (`cmdb_relationships`): "source *type* target" with types depends on, runs on, uses, is part of, connects to.
- **Link** (`cmdb_ci_links`): a record of another module (a ticket) linked to an item, through Core's `ConfigurationItems` contract.

## Impact

`ImpactAnalysis::affectedBy()` and `dependenciesOf()` walk the relationships with a recursive CTE: at most 10 steps, cycles cut, retired and deleted items skipped, nearest and most critical first. A failing target affects the source for depends on, runs on and uses; a failing part affects the whole; connects to propagates nothing.

## Screens

- `/cmdb`: list with search (name, identifier, attribute values) and filters by type, status, environment and criticality.
- `/cmdb/new`, `/cmdb/{id}/edit`: form; the attribute fields follow the type.
- `/cmdb/{id}`: details, relationships (add, remove), dependency map (Cytoscape.js), affected items, needed items, linked tickets, assets and business processes.
- `/cmdb/import`: CSV/XLSX import of items or relationships with preview; all rows or none.
- Administration → CMDB → Item types.
- Ticketing: the **Configuration items** panel on the desk ticket page, and the same list, read-only, on the CAB review page.

## Permissions

| Permission | Default roles | Purpose |
|---|---|---|
| `cmdb.ci.view` | agent, team-lead, cab-member | See items, relationships and impact |
| `cmdb.ci.manage` | agent, team-lead | Create, update, delete, relate, import, link to tickets (with the ticket's `work` right) |
| `cmdb.type.manage` | team-lead | Item types and their attributes |

Administrators have every permission.

## API (ADR 0062)

Abilities: `cmdb:read`, `cmdb:write`. Permissions are the screens' (`cmdb.ci.view`, `cmdb.ci.manage`).

| Method | Path | |
|---|---|---|
| GET | `/api/v1/cmdb/types` | Types and their attribute schemas |
| GET | `/api/v1/cmdb/items` | Filters `type` (key or id), `status`, `environment`, `criticality`, `owner_id`, `team_id`, `search`; sorts `name`, `identifier`, `criticality`, `created_at`, `updated_at` |
| POST | `/api/v1/cmdb/items` | Through `SaveConfigurationItem`; `properties` are checked against the type's schema (errors under `properties.<key>`) |
| GET | `/api/v1/cmdb/items/{id}` | With `outgoing` and `incoming` relationships |
| PATCH | `/api/v1/cmdb/items/{id}` | Only the fields sent; `properties` merged by key |
| DELETE | `/api/v1/cmdb/items/{id}` | Soft delete |
| POST | `/api/v1/cmdb/relationships` | `source_id`, `type`, `target_id`; an identical relationship is returned as is (200) |
| DELETE | `/api/v1/cmdb/relationships/{id}` | |
