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

## Change advisory boards

Under **CAB boards**, create the boards that authorize changes, and choose their members.

- A **regular** board authorizes normal changes. Every member except the requester must approve.
- Tick **Emergency CAB (ECAB)** for the board that authorizes emergency changes, such as a critical security patch. Keep it small, so it can decide in minutes.

Members must belong to the organization. Only people with the **CAB member** or **Team lead** role are asked to decide; give that role to anyone else you add. Membership changes are recorded in the audit trail.

Boards cannot be deleted, only deactivated. A deactivated board receives no new changes, but members can still decide on the changes already waiting for it. Normal changes need an active regular board, and emergency changes need an active ECAB.

## Standard changes

Under **Standard changes**, describe routine work that does not need the CAB each time: when it applies, its risk, and its implementation and rollback plans. Approving a template is a decision to pre-authorize that work, so keep templates to low-risk, well-known procedures. Agents choose a template when they create a standard change, and the change is authorized at once.

Deactivate a template to stop its use without losing its history.

## Mailboxes (email-to-ticket)

Administrators connect support mailboxes under **Mailboxes**, so people can raise tickets and reply by email. The application reads them over IMAP every minute.

- **Email address:** the support address people write to. Ticket emails use it as their reply address, so replies come back to the ticket.
- **IMAP server, port, encryption:** for Exchange, usually port 993 with SSL/TLS, or 143 with STARTTLS. Unencrypted connections are refused. Only the ports allowed by the installation can be used, and addresses such as `localhost` are refused.
- **Username and password:** a mailbox account with read access. The password is stored encrypted and never shown again; leave it empty when editing to keep it.
- **Folder to read:** usually `INBOX`. **Move processed messages to:** an existing folder; empty means processed messages are only marked as read.
- **New tickets are** incidents or service requests, in the chosen **queue** (empty means the default queue).

**Test connection** checks the server, the sign-in and the folders. The list shows when each mailbox was last read and, if reading failed, why.

Only people with an account in the organization can raise tickets by email; other senders are ignored without an answer. Automatic messages (out-of-office replies, delivery failures, mailing lists) are ignored too, to prevent mail loops.

**Incoming email** lists every message read and what happened to it. Entries are deleted after 90 days. A message that failed is marked as read in the mailbox; mark it as unread there to have it read again.

