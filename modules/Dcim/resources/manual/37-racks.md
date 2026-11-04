---
title: Racks
audience: agents
---

**Configuration & assets → Racks** shows the racks of every room and how full they are. Rooms are the organization's locations (a site, building, floor or room), kept under **Administration → Organization → Locations**.

## Adding a rack

Asset managers and team leads click **New rack** (or **Add a rack here** under a room) and give:

- the **room**;
- a **name** unique in the room;
- optionally its **row** and **position in the row**;
- its **height** in units (U), usually 42;
- the **power available** to it, in watts.

A rack cannot be made shorter than the equipment it holds.

## What is mounted in it

The rack page draws the rack **from the front** and **from the rear**, top unit first. The equipment are assets of the asset register; to mount one:

1. click a free unit on the drawing (or fill in the form on the right);
2. search the asset by tag, name, serial number or model;
3. give the **lowest unit** it sits on and the **units it takes**, the side it is **mounted from**, and whether it is **full depth**: a full-depth server takes its units on both sides, a half-depth patch panel only on its own side, so another device can sit behind it;
4. optionally its **power draw**, used to plan the rack's power.

Leave the lowest unit empty for equipment on the side of the rack that takes no unit (0U), such as a vertical PDU.

Two pieces of equipment never share a unit on the same side: the page says what is in the way. An asset is mounted in one rack only; **Move or edit** changes its place, **Take out** removes it from the rack (it stays in the asset register).

Mounting an asset records the rack's room as the asset's location in the asset register, and the asset's page says which rack and units it is in.

## Printing and exporting

**Print the rack** on a rack's page gives a sheet to take into the room:

- the rack from the front and from the rear;
- the equipment with serial numbers, power draw and supplies, with single-fed equipment marked;
- the cables that reach the rack, with their far ends.

Print it, or **Download PDF** where the PDF service is installed.

**Export to spreadsheet** on the Racks page prepares a workbook of the whole data centre: racks, equipment, cables, the power chain with its load, and IP addresses. It appears in the list below the button when ready.

The dashboard's **Data centre** group shows rack space used, power sources over capacity or under high load, equipment with a single power supply, and nearly full IPv4 subnets.
