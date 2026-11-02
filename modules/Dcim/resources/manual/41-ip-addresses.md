---
title: IP addresses
audience: agents
---

**Configuration & assets → IP addresses** lists the organization's subnets in network order. A subnet inside a larger one appears indented under it. For IPv4 subnets the list shows how full each one is; for IPv6 it shows how many addresses are recorded.

Search by an **address** (10.20.1.50) to see which subnets hold it and whether it is recorded, by a **network** (10.20.0.0/16), or by a word of the purpose or VLAN name.

## Subnets and VLANs

Asset managers and team leads click **New subnet** and write the network with its prefix length, for example `10.20.1.0/24` or `2001:db8:10::/64`. They can also give:

- a **gateway**, which must be inside the network;
- a **VLAN**;
- the **site or room**;
- a **purpose**.

If you type an address with a prefix, such as `10.20.1.5/24`, the form suggests the network you meant, `10.20.1.0/24`. The same network cannot be recorded twice.

**VLANs** (from the IP addresses page) have an ID from 1 to 4094 and a name, for one site or for the whole organization. An ID is used once per site. A VLAN with subnets cannot be deleted.

## Giving out an address

On a subnet's page, under **Add an address**:

1. write the address, or click **Next free** for the first one nobody holds; the gateway is skipped;
2. choose its **status**: in use, reserved, or handed out by DHCP;
3. optionally its **DNS name**;
4. under **Used by**, search for equipment in a rack or an item of the CMDB.

An address must sit inside a recorded subnet, and is recorded once. For IPv4, the network and broadcast addresses cannot be given out. The equipment's page lists its addresses.

A subnet's page shows its own addresses; those of the subnets inside it are listed on their pages. A subnet cannot be deleted while subnets or addresses inside it depend on it.
