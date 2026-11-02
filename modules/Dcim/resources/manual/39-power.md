---
title: Power
audience: agents
---

**Configuration & assets → Power** shows how each room is powered: the **feeds** (utility or generator), the **UPSs** they feed, and the **PDUs** under the UPSs. Each one shows how much power it carries.

## Recording the chain

Asset managers and team leads click **New power source** and give:

- its **type** (feed, UPS or PDU), a **name** and the **room**;
- its **side**, A or B, if the room has two independent paths;
- what it is **fed by**: a PDU by its UPS, a UPS by its feed;
- its **capacity** in watts: a UPS's rating, or for a PDU its breaker (16 A × 230 V ≈ 3,680 W);
- for a UPS or PDU, the **asset** in the asset register and, for a PDU, its **rack**.

Start with the feeds, then the UPSs, then the PDUs. A chain cannot loop back on itself, and a source that still feeds something cannot be deleted.

## Plugging equipment in

Each device's page (**Ports and cables** on the rack) has a **Power** section. Choose the PDU or UPS under **Plug into**, optionally the **outlet**, and click **Plug in**. Do it once for each power supply the device has.

The section says whether the device has **two independent supplies**: two supplies that do not depend on the same UPS or feed. Two PDUs behind the same UPS are **not** independent: if that UPS fails, both go dark.

The load uses each device's **power draw**, recorded on the rack page under **Move or edit**. Devices without one are counted separately.

## Reading the load

For each source:

- the **load** in normal operation: equipment with two supplies draws half from each;
- **if the other side fails**: the source then carries all of that equipment.

The bar turns amber from 80% of capacity, now or if the other side fails, and red when the source is over capacity in either case.

## What fails if it fails

A source's page lists:

- the equipment that **loses power** if it fails;
- the equipment that **keeps running on its other supply**;
- the services in the **CMDB** that depend on the equipment that loses power.

Equipment with a single supply that the CMDB relies on, and sources that would be overloaded if the other side failed, are suggested to risk managers in the risk register.
