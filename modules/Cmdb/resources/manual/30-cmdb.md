---
title: CMDB (configuration items)
audience: agents
---

The **CMDB** (configuration management database) records everything IT runs, the *configuration items*, and how they depend on each other. With it you can answer two questions quickly:

- **If this fails, what else stops working?**
- **What does this need to work?**

Open **Configuration & assets → CMDB** in the menu. IT staff can see it; agents and team leads keep it up to date.

## Items

Every item has a **type**: business service, application, physical server, virtual machine, database, network device, storage, cloud service, and any type your administrators add. Each type has its own attributes, for example the operating system and memory of a server.

Every item also has:

- an **identifier** (optional): hostname, asset tag or another unique name, unique in your organization regardless of upper or lower case. Imports use it to find the item;
- a **status**: planned, in use, in maintenance, retired. Retired items are left out of impact analysis;
- an **environment** (production, test, development, disaster recovery), a **criticality** (low to critical), an **owner** and a **support team**.

**New item** creates one: choose the type first, and its attributes appear. Search finds items by name, identifier or attribute values (for example an IP address). Every change is recorded in the audit trail.

Under **Recovery capability**, record what IT can deliver if the item fails: how fast it can be back, and how much recent data could be lost. The business impact analysis compares them with what the business requires, and its **Recovery gaps** page shows where they fall short.

Prefer the status **Retired** to **Delete**: a retired item keeps its history and its links to past tickets.

## Relationships

On an item's page, **Add a relationship** links it to another item. Read it as a sentence:

| Relationship | Example | When the right-hand item fails… |
|---|---|---|
| depends on | *payroll* depends on *payroll-db* | the left-hand item is affected |
| runs on | *payroll-app* runs on *vm-07* | the left-hand item is affected |
| uses | *intranet* uses *ldap-01* | the left-hand item is affected |
| is part of | *node-2* is part of *cluster-a* | — (when the left-hand part fails, the whole is affected) |
| connects to | *switch-01* connects to *firewall-01* | nothing is propagated |

Choose **… (this item)** to put the item you are on at the right-hand side of the sentence.

## Impact

The item page lists:

- **Affected if this fails**: everything that stops working or degrades, directly or through other items, nearest first and, at the same distance, the most critical first;
- **What this needs**: everything it depends on.

Cycles (A needs B, B needs A) are handled, and the analysis stops after 10 steps. The **dependency map** draws the item and its neighbours up to two steps away; colours show criticality, and a click opens an item.

## Tickets

On a ticket, **Configuration items** links the affected items: type a name or an identifier and click the item. The panel then shows what their failure affects. This is how an incident on a server shows the services it takes down, and how a change shows the CAB what it touches. Each item's page lists its tickets.

## Importing

**Import** fills the CMDB from a CSV or XLSX file. Choose what the file contains:

- **Items**, one row per item. The first row holds the column names: `type`, `name`, `identifier`, `status`, `environment`, `criticality`, `owner` (an email), `team` (a team name), `description`, and the keys of the type's attributes (the import page lists them). A row whose identifier exists updates that item, and an empty cell keeps its current value; the other rows create items.
- **Relationships**, with the columns `source`, `relationship` and `target`: the identifiers of two existing items and the relationship (for example `runs on`).

**Preview** shows what each row will do and saves nothing. If any row has an error, only the faulty rows are shown, with the reason, and nothing can be imported until the file is fixed. Then the whole file is imported at once.
