# ADR 0050 — IP address management on PostgreSQL network types

- **Status:** Accepted
- **Date:** 2026-12-12

## Context

The owner asked for subnets (IPv4 and IPv6) with VLAN, gateway and purpose, and addresses given to devices and CMDB items, with utilisation, the next free address and no duplicates. CLAUDE.md asks us to use PostgreSQL's own features.

## Decision

- **Network types:** subnets are stored as `cidr` and addresses as `inet`, each with a GiST `inet_ops` index:
  - containment is answered by the database (`<<`, `>>`, `<<=`);
  - "which subnet holds this address", the most specific one, is an indexed query;
  - IPv4 and IPv6 sit in the same columns.
- **Hierarchy by containment:** a subnet's parents and children follow from the networks themselves, with no `parent_id` to keep in sync. Two CIDR blocks either nest or are disjoint, so partial overlaps cannot exist.
- **Integrity in the database:**
  - one subnet per network per organization and one record per address per organization (unique indexes);
  - an address is a single host (a CHECK on the mask length);
  - a gateway is a host inside its network (CHECK);
  - an address is held by equipment *or* a CMDB item, not both (CHECK).

  The application checks first to explain itself:
  - "10.20.0.5/24 is an address inside a network — did you mean 10.20.0.0/24?";
  - "no subnet holds this address";
  - the network and broadcast addresses of IPv4 subnets wider than /31 are refused.
- **Next free address:** the first host of the subnet, from a `generate_series` over the first 65,536 offsets, that no record holds and that is not the gateway. It works for IPv6 too, starting at the subnet's first address.
- **Utilisation:** for IPv4, the addresses recorded inside the subnet at any depth against its usable hosts. For IPv6 only a count, because a percentage of 2⁶⁴ means nothing.
- **VLANs** (`dcim_vlans`): ID 1–4094, name, and the site or room they apply to; the ID is unique per site (`NULLS NOT DISTINCT` covers organization-wide VLANs).
- **Holders:**
  - a mount (a foreign key, set to null when the equipment leaves the rack);
  - or a CMDB item through Core's `ConfigurationItems`, with a new `find(ids)` method and no cross-module key.

  The equipment page lists its addresses.
- **Screens:**
  - *Configuration & assets → IP addresses*: subnets indented by containment with utilisation, and a search by address, network or purpose;
  - VLANs;
  - subnet form;
  - subnet page with the subnets inside it, its own addresses, and the form with **Next free**.

  Same permissions as the rest of DCIM; everything is audited.

## Alternatives considered

- **Addresses and networks as text or numbers in PHP:** portable, but containment, ordering and IPv6 would all be hand-written and slower.
- **A stored parent per subnet:** simpler queries, but it must be recomputed whenever a subnet is added or removed in between.
- **VRFs (overlapping private ranges per routing domain):** needed by some networks, but not asked for. Uniqueness per organization covers most companies, and a VRF column can come later.

## Consequences

- Discovery (scanning, DHCP or DNS sync) is not included; addresses are recorded by people or, later, by an import.
- A subnet cannot be removed while addresses inside it would be left without a subnet.
