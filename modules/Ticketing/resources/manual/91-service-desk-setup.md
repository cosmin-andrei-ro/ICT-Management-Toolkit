---
title: Configuring the service desk
audience: administrators
---

Team leads and administrators configure the service desk in **Administration → Service desk**.

## Queues

A queue is where tickets wait for a team.

- Each queue belongs to a **team**. Only members of that team can be assigned its tickets.
- Mark one queue as the **default queue**: tickets without a queue (for example from the portal) land there. Only one queue can be the default.
- Deactivate a queue instead of deleting it, to keep history readable.

## Categories

Categories classify tickets for routing and reporting. They have two levels, for example *Hardware › Printers*. Requesters can pick one when they open a ticket.

## Priority matrix

Priority comes from **impact × urgency**. The default follows ITIL:

| Impact \ Urgency | High | Medium | Low |
|---|---|---|---|
| **High** | Critical | High | Medium |
| **Medium** | High | Medium | Low |
| **Low** | Medium | Low | Low |

Change any cell under **Priority matrix**. Only the cells you change are stored, and every change is recorded in the audit trail. New values apply to new and re-triaged tickets.

## SLA targets

Under **SLA targets**, set the first-response and resolution targets per priority, in working minutes (480 minutes = one working day). Resolution can never be shorter than first response. Working time follows the default **business calendar** (*Administration → Organization*).

## Service catalog

Under **Service catalog**, each service has:

- a name and a description shown to people;
- a **category** and a **queue** for routing (empty means the default queue);
- the default **impact** and **urgency**, which set the priority of requests;
- an optional **owner**;
- the **request form**: a list of questions.

Each question has a label, a **key** (lowercase letters, digits and underscores, unique in the form, for example `laptop_model`), a type, an optional help text, and whether it is required. Choice questions need their list of choices.

| Type | Use it for |
|---|---|
| Short text | A name, an asset tag. |
| Long text | Explanations. |
| Number | Quantities. |
| Date | "Needed by". |
| Choice | A fixed list of options. |
| Checkbox | Confirmations, such as accepting a policy. |

Editing a form never changes requests already made: each request keeps the questions and answers as they were.

Deactivate a service to remove it from the catalog without losing its history.
