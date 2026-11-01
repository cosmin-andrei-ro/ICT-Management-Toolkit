---
title: CMDB setup
audience: administrators
---

## Item types

Under **Administration → CMDB → Item types**, team leads and administrators define what kinds of items the CMDB holds and what each one records.

Every organization starts with eight types: business service, application, physical server, virtual machine, database, network device, storage and cloud service. Change them freely: rename them, add or remove attributes, or deactivate the ones you do not use.

- The **key** (for example `server`) identifies the type in imports. It cannot be changed after the type is created.
- **Attributes** are built like service request forms: a label, a key, a type (short text, long text, number, date, choice, checkbox), whether it is required, and a help text. Values already recorded stay when you change the attributes; removed attributes are simply no longer shown.
- **Inactive** types cannot be chosen for new items. Existing items keep them.
- A type can be deleted only while no item uses it.

## Who does what

| Role | CMDB |
|---|---|
| Agent | Sees items, creates and updates them, links them to tickets, imports |
| Team lead | The same, plus item types |
| CAB member | Sees items and the impact of the changes they review |
| Requester | No access |
