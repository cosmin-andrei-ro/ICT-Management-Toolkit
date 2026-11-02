---
title: Ports and cables
audience: agents
---

On a rack's page, **Ports and cables** next to a device opens its ports: the cable on each one and what is at the other end.

## Adding ports

Asset managers and team leads add ports under **Add ports**:

- one name, such as `eth0`;
- or a **series** with a range in brackets: `Gi1/0/[1-48]` adds Gi1/0/1 to Gi1/0/48, and `[01-24]` adds 01 to 24, keeping the leading zero.

Each port has a **type** (RJ45, SFP, LC fibre, console…) and a **side** (front or rear). For a **patch panel**, tick *Patch panel*: each front port gets a rear port ("01 rear") wired to it. A connection then continues through the panel from one side to the other.

A port with a cable cannot be deleted; disconnect it first. Taking a device out of its rack removes its ports and cables.

## Connecting

Click **Connect** on a free port, search the device at the other end (by name, asset tag or rack), choose its free port, then give the cable's **label**, **type**, **colour**, **length** and **status**. A cable can be *Planned*, for one to be laid.

A port takes one cable, and two cables cannot have the same label. **Edit cable** changes its details; **Disconnect** removes it.

## Following a connection

**Trace** shows where a connection leads. For example, a server's `eth0` reaches cable C-001, goes to patch panel A port 1, through the panel to its rear, along the trunk to patch panel B, then to the switch's Gi1. The path stops at a port with no cable.

**Configuration & assets → Cables** lists every cable with both ends; search it by label.
