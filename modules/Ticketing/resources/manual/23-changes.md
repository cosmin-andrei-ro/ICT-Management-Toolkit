---
title: Changes
audience: agents
---

A **change** is planned work on a live system: a firmware upgrade, a new firewall rule, a server migration. Recording the plan and getting it authorized first means fewer surprises and a clear way back if something goes wrong. Changes get numbers such as `CHG-000012`. Only service desk staff create them, and requesters never see them.

## Three kinds of change

| Kind | When | Who authorizes |
|---|---|---|
| **Standard** | Routine, low-risk work with a known procedure, such as restarting the print spooler. | Nobody each time: the procedure was approved once, as a template. |
| **Normal** | Everything planned that is not routine. | The **CAB** (change advisory board). |
| **Emergency** | Urgent work that cannot wait for the CAB, such as a critical security patch. | The **ECAB** (emergency CAB), a small board that can decide quickly. A review afterwards is required. |

## Creating a change

Open **Change calendar** in the menu and click **New change**.

- **Standard:** choose the template and the planned window. The plans come from the template, and the change is authorized at once.
- **Normal** or **Emergency:** give a title, why the change is needed, the risk, and the plan:
  - **Implementation plan:** the steps;
  - **Rollback plan:** how to undo it if it goes wrong;
  - **How to verify it worked** (optional);
  - **Planned window:** when it starts and ends. Times are in the organization's time zone, shown under the fields.

The plans and the window can be completed later, on the change page. A normal or emergency change starts as a **Draft**.

## Getting it authorized

On the change page, in the **Change** panel, choose the board and click **Submit for approval**. You need the implementation plan, the rollback plan and the window first. Normal changes go to a regular CAB, and emergency changes go to an ECAB.

Every member of the board must approve, except you: nobody approves their own change. Members get an email with a link. The panel shows each decision as it comes in. You get an email when the change is **Authorized**, or **Rejected** with the reason.

If it is rejected, adjust the plan and submit it again: a new round starts, and everyone decides again.

**Editing the plan or the window after submission withdraws the approvals.** The change goes back to Draft, and the board must approve what will actually be done. For a standard change, only the window can be changed.

## Implementing

A change cannot be set to **In progress** or resolved until it is authorized. Work in the window, then resolve it with **Change implemented** or **Change rolled back**, and a note on what happened.

Link the configuration items the change touches in the **Configuration items** panel (see the CMDB page of this manual). The panel shows what a failure of those items would affect, and the CAB sees the same when reviewing the change.

If other submitted or authorized changes overlap the window, the change page lists them. Check that they do not touch the same systems, or agree on the order with their owners.

## Review after an emergency change

An emergency change cannot be closed, not even automatically, until someone writes its **Post-implementation review** on the change page: what happened, why it was urgent, and what to improve. Once it is resolved, the review form appears in the Change panel. You can write a review for any other change too; there it is optional.

## The change calendar

**Change calendar** shows every submitted and authorized change of the next 30 days, by day, in the organization's time zone. Changes whose windows overlap are marked **Overlaps**. Pick another start date to look further ahead.
