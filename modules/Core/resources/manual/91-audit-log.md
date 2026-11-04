---
title: History and the audit log
audience: agents
---

Everything that happens in the toolkit is recorded in the **audit log**: who changed what and when, who signed in, who changed someone's rights, who revealed a secret or exported data. Nobody can change or delete an entry, not even an administrator.

## The history of a record

Most record pages (tickets, configuration items, assets, licences, risks, controls, findings, audits, continuity plans, processes, applications, projects, documentation pages, racks, suppliers, contracts…) end with a **History** section.

1. Select **Show history**.
2. Each change shows what happened (created, changed, deleted), who did it and when, and for every field its value **before** and **after**.

You see the history of the records you may change. Readers of the audit log see the history of every record they can open.

People appear by name. Other references, such as a category or a team, are shown as a short identifier: the log keeps what was stored at the time.

## The audit log page

The **Audit log** page (menu **Compliance & audit**) is for administrators, team leads, security officers, compliance managers and auditors.

By default it shows **security events**:

- sign-ins, sign-ins that failed or were refused, and sign-outs;
- roles and permissions given or removed;
- two-factor authentication turned on, off or reset, and passkeys added or removed;
- password changes and resets, and invitations;
- revealed secrets, such as a licence key;
- exports requested and downloaded.

Choose **Changes to records** or **Everything** to see changes to records as well. You can narrow the list by event, by person (name or e-mail), by record type and by date. Failed sign-ins are shown in red.

**Export to spreadsheet** prepares a workbook of what the filters show, one row per changed field, with the IP address and the request identifier. The export itself is recorded in the log. When it is ready, a download link appears at the top of the page for seven days.

Failed sign-ins for accounts that do not exist belong to no organization, so only administrators see them.
