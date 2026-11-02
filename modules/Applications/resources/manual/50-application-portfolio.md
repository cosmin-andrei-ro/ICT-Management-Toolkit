---
title: Application portfolio
audience: everyone
---

**Configuration & assets → Application portfolio** lists every application of the company with its technical documentation: the single point of reference for how it is built, run and supported. The documentation follows the company's template, chapter by chapter.

Application managers register applications; each application has a **business owner**, an **IT / application owner** and, optionally, a **deputy**, who keep its documentation up to date. Owners who are not IT staff find their applications under **My applications**. IT staff read every application.

## Register an application

Application managers click **New application** and enter its name and code (for example *Ticketing Platform*, *APP-TKT*), the department that owns it with its e-mail, and the owners.

## Write the documentation

Open the application and click **Write the documentation** (later **Update the documentation**). The sheet opens on **Document control**; the chapters are listed on the left. Moving to another chapter saves the one you leave.

- Grey examples show what is expected; they disappear when you type.
- Dropdowns offer the company's values; where **Other…** is offered, choose it and type your own value.
- In tables, **Add a row** adds one; empty rows are dropped when saving.
- Chapters still missing a required value are marked *to complete*.

> Never store passwords, keys or secrets in the documentation - reference the vault location only.

### Data, flows and roles (chapters 2 and 3)

- **2.1 Data categories**: tick, for every category of data the application stores, processes or transmits, the elements concerned (personal data, payment card data, health records…), and set the overall classification to the most sensitive one.
- **2.4 System integrations**: one row per automated exchange with another system. The **data flow diagram** in 2.2 is drawn from these rows: systems sending data on the left, systems receiving it on the right, exchanges both ways below.
- **Your own diagram**: in 2.2 you can also attach an image of your own diagram (PNG, JPEG, WebP or GIF) and its .drawio file. Files stay with the application, so later versions keep them; remove them from a version if they no longer apply.
- **3.4 Role permissions matrix**: list the functionalities in 2.3 and the roles in 3.3, save, then tick which role may use which functionality.

At the bottom, describe **what changed in this version** (for the first one, for example *Initial documentation of the existing application*).

## Major and minor changes

- The **first version**, and any version marked **Major change** (architecture, data, security, users or integrations), is **submitted for approval**. The business owner, the IT / application owner and a security officer each approve it; an application manager may approve in place of an owner. Nobody approves a version they submitted, and one person gives one approval. Any approver may **return** it with a reason; the approvals given are then cleared and the owners correct it.
- A **Minor change** (corrections, small updates) is **published** at once and logged in the change history.

When a version comes into force it gets its number — 1.0 for the first, then 1.1, 1.2… for minor changes and 2.0, 3.0… for major ones — and the next review is due a year later. The application page shows the **change history**: version, date, author, description and approvals.

## Dropdown values

Application managers adjust the dropdowns in **Administration → Application portfolio → Dropdown values**: rename a value, change the order, add values, or deactivate one that should no longer be offered. Documentation that already uses a value keeps it.
