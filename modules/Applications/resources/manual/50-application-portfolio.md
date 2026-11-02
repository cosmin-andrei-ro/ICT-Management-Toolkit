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

### Network, infrastructure and software (chapters 4 to 7)

- **4.3 Firewall rules & ports**: one row per rule or network flow, with the change ticket that opened it. The **network diagram** in 4.2 is drawn from these rows - one box per source or destination, one arrow per rule with its port and protocol - so use the same names everywhere. You can attach your own diagram too.
- **5.2 Components**: list the servers and other components; also **link the CMDB items** the application runs on (search by name). Their CMDB page then shows the application, and the next version keeps the links.
- **6 Software & platforms**: architecture, backends, frontends, databases (attach the database diagram in 6.4.1), cache and middleware, third-party libraries with their licences and end of support.
- **7 Technical users / service accounts**: every non-personal account, with where its credential is kept (vault) - **never the password itself**.

### Security, operations and continuity (chapters 8 to 16)

- **8.1 Certificates**: enter the **expiry date** too - the IT owner and the deputy get an e-mail about a month before a certificate of the version in force expires.
- **8.2 Secrets**: where each secret is kept and how often it is rotated - never the secret.
- **10.1 Business impact**: link the business processes the application supports. Their recovery objectives from the business impact analysis are shown next to the fields: the application must be back within the strictest RTO.
- **10.3 Dependencies** and **10.5 Restore**: what the components linked in 5.2 depend on, and the continuity and recovery plans that cover them, are shown from the CMDB and the plans.
- **11 to 14**: deployment and rollback, scheduled jobs, the runbook (start / stop order, health checks, administrative tasks) and known issues - the most valuable chapter for newcomers.
- **15 Decommissioning**: when the application is retired, **Add the template's steps** fills the checklist; record the ticket and status of each step.
- **16 Glossary**: the standard terms are listed; add the application's own.

At the bottom, describe **what changed in this version** (for the first one, for example *Initial documentation of the existing application*).

## Major and minor changes

- The **first version**, and any version marked **Major change** (architecture, data, security, users or integrations), is **submitted for approval**. The business owner, the IT / application owner and a security officer each approve it; an application manager may approve in place of an owner. Nobody approves a version they submitted, and one person gives one approval. Any approver may **return** it with a reason; the approvals given are then cleared and the owners correct it.
- A **Minor change** (corrections, small updates) is **published** at once and logged in the change history.

When a version comes into force it gets its number — 1.0 for the first, then 1.1, 1.2… for minor changes and 2.0, 3.0… for major ones — and the next review is due a year later. The application page shows the **change history**: version, date, author, description and approvals.

## Dropdown values

Application managers adjust the dropdowns in **Administration → Application portfolio → Dropdown values**: rename a value, change the order, add values, or deactivate one that should no longer be offered. Documentation that already uses a value keeps it.
