# DCIM module

Data centre infrastructure: racks and what is mounted in them; then cabling, power and IP addresses. Code: `modules/Dcim/`. Decisions: [ADR 0047](../adr/0047-dcim-racks-and-itam-assets.md), [ADR 0048](../adr/0048-dcim-ports-cables-and-paths.md), [ADR 0049](../adr/0049-dcim-power-chain.md), [ADR 0050](../adr/0050-dcim-ipam.md), [ADR 0051](../adr/0051-dcim-indicators-exports-and-rack-sheet.md).

## Concepts

- **Room**: a Core location (site › building › floor › room).
- **Rack** (`dcim_racks`): room, name (unique per room), row, position, height (U), power available, notes. Soft deleted; deleted only when empty.
- **Mount** (`dcim_mounts`): an asset of the asset register (`asset_id`, through Core's `PhysicalAssets`), lowest unit (`position_u`, null for 0U), units taken, face (front/rear), full depth, power draw, notes. Generated `front_units` / `rear_units` ranges with exclusion constraints keep units free; triggers keep mounts inside their rack.
- **Port** (`dcim_ports`): of a mount; name (unique per device), type, side, position, `rear_port_id` (a patch panel's front port → its rear port, one to one). Removed with the mount.
- **Cable** (`dcim_cables`) and its two **ends** (`dcim_cable_ends`, unique `port_id`: one cable per port): label (unique per organization when set), type, colour, length, status (connected / planned), notes.
- **Power source** (`dcim_power_sources`): feed, UPS or PDU in a room; side A/B; fed by (`upstream_id`, no loops); asset in the register; a PDU's rack; capacity (W).
- **Power connection** (`dcim_power_connections`): a mount plugged into a PDU or UPS, with an outlet (unique per source).
- `PowerGrid` computes normal and worst-case load per source, independent supplies (no shared source above), and what a source's failure takes down.
- **VLAN** (`dcim_vlans`): ID 1–4094 unique per site (or organization-wide), name, description.
- **Subnet** (`dcim_subnets`): `network` cidr (unique), `gateway` inet (inside it), VLAN, site or room, purpose, notes; nested by containment (GiST index).
- **IP address** (`dcim_ip_addresses`): `address` inet (a single host, unique per organization), status (in use / reserved / DHCP), DNS name, held by a mount or a CMDB item, description.
- `CablePath::from($port)` follows cables and patch panel pass-throughs to the end of a connection.

## Screens

- `/dcim/racks` (**Configuration & assets → Racks**): racks by room with their occupancy.
- `/dcim/racks/new[?location=]`, `/dcim/racks/{id}/edit`: the rack form.
- `/dcim/racks/{id}`: the rack from the front and from the rear; mounted equipment; mount, move, take out.
- `/dcim/racks/{id}/print` and `/pdf`: the rack sheet (both sides, equipment with serials, draw and supplies, cables).
- **Export to spreadsheet** on the Racks page: racks, equipment, cables, power and IP addresses (`dcim.inventory`).
- `/dcim/equipment/{mount}`: a device's ports, their cables and far ends, trace, connect / edit / disconnect, add ports (series, patch panel).
- `/dcim/power` (**Configuration & assets → Power**): the chain by room with load bars; `/dcim/power/new`, `/{id}`, `/{id}/edit`: a source, what it feeds, its load, what fails with it (equipment and CMDB items).
- The equipment page has a **Power** section: supplies, redundancy, plug in / unplug.
- `/dcim/ipam` (**Configuration & assets → IP addresses**): subnets nested by containment with utilisation; search by address, network or purpose. `/dcim/ipam/vlans`; `/dcim/ipam/subnets/new`, `/{id}`, `/{id}/edit`: a subnet, the subnets inside it, its addresses, **Next free**.
- The equipment page lists its IP addresses.
- `/dcim/cables` (**Configuration & assets → Cables**): every cable, searchable by label.
- The asset's page in the asset register shows its rack and units.

## For other modules

- Implements Core `RackPlacements` (`MountedAssets`); uses `ConfigurationItems::find()` and `impactOfItems()`.
- Uses Core `PhysicalAssets` (implemented by ITAM) and dispatches Core `AssetPlaced` (ITAM records the room as the asset's location).

## Indicators

Source `dcim` in Core `Metrics`: `dcim.rack_space_used` (%), `dcim.power_over`, `dcim.power_high`, `dcim.single_fed`, `dcim.subnets_full` (IPv4 ≥ 90%). They appear on the dashboards, in the trends and in the monthly management report.

## For the risk register

Signals of source `dcim`: single-fed equipment linked to CMDB items (`dcim.single_fed:<mount>`), sources overloaded if the other side failed (`dcim.overload:<source>`).

## Configuration

- `DCIM_POWER_WARN_PERCENT` (80): load share of capacity from which a source needs attention.

## Permissions

| Permission | Default roles | Purpose |
|---|---|---|
| `dcim.infrastructure.view` | agent, team-lead, asset-manager, continuity-coordinator | See racks |
| `dcim.infrastructure.manage` | team-lead, asset-manager | Add and change racks; mount equipment |

## Plan

1. Racks, mounting assets of the register, front and rear drawing — **done**.
2. Ports and cables with path tracing through patch panels — **done**.
3. Power: UPS, PDUs, A/B feeds, load and what fails with a UPS — **done**.
4. IPAM: VLANs, subnets, addresses — **done**.
5. Indicators, exports, suggested risks, rack sheet to print — **done**.

## API (ADR 0065)

`dcim:read`, permission `dcim.infrastructure.view`. Read-only:

- `GET /api/v1/dcim/racks` and `/racks/{id}`: equipment top to bottom, with the ITAM asset tag and name;
- `/dcim/power`: capacity, and normal and worst load from `PowerGrid`;
- `/dcim/subnets`: VLAN and next free address;
- `/dcim/addresses`: filters `subnet_id`, `status`, `search`.
