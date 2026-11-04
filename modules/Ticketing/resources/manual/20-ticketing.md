---
title: Working in Ticketing
audience: agents
---

The **All tickets** page is the agent's working list. It shows every ticket of your organization; open tickets come first, most urgent first.

## Finding tickets

| Filter | Use it to |
|---|---|
| **Search** | Find by ticket number or words in the title and description. English word forms match: "printers" finds "printer". |
| **Type** | Show only incidents, service requests or problems. |
| **Status** | Show open tickets (default), all tickets, or one status. |
| **Queue** | Show one team's queue. |
| **SLA** | Show tickets *at risk or overdue*, or only *overdue*. |
| **Assignee** | Show your tickets (*Me*) or unassigned ones. |

The **Due** column shows the next service-level deadline: the first-response deadline until someone has replied, then the resolution deadline. Red means overdue, amber means three quarters of the time are used, and *Paused* means you are waiting for the requester.

## Working a ticket

Open a ticket to see everything about it: details, service request answers, the full conversation including internal notes (if your role lets you read them), attachments, SLA clocks and status history.

### Taking a ticket

Click **Assign to me**, or choose a queue and a person under **Assignment**. Only members of the queue's team can be assigned. Moving a ticket to **In progress** also assigns it to you if nobody owns it.

### Talking to the requester

- Write in **Reply** and click **Send**. The requester gets an email with a link.
- Tick **Internal note** to write something only IT can see. Attachments sent with an internal note are internal too.
- Your first public reply stops the **first response** clock.

### Statuses

Use the **Status** buttons:

- **In progress:** you are working on it.
- **Pending:** you are waiting for the requester. This pauses the SLA clocks; the requester's reply resumes work automatically.
- **New:** hand it back to the queue.

### Triage

Under **Triage** you can correct the summary, details, category, impact and urgency. The priority is recalculated from impact and urgency, and so are the SLA deadlines. Nobody sets the priority by hand.

### Resolving

Under **Resolve**, choose what happened and describe what you did. The requester sees this note.

| Resolution | When to use it |
|---|---|
| Fixed | The cause was fixed. |
| Workaround provided | The user can work, but the cause remains (see *Problems and known errors*). |
| Request fulfilled | A service request was delivered. |
| Could not reproduce | Nothing wrong could be found. |
| Duplicate | Already reported in another ticket. |
| User error / training | The system works; the user needed help. |
| No action needed | Nothing had to be done. |

The requester can confirm or reopen. Resolved tickets close automatically after 5 days. **Cancel ticket** (with a reason the requester sees) is for tickets that should not be worked on at all.

## Knowledge base

The ticket page shows the **knowledge base** articles linked to the ticket, and others that may help, found from its title. To link an article, open it and add the ticket under **About**. Requesters see the articles linked to their ticket that they may read - a guide for employees, not an internal procedure. Known errors, being problems, show their articles the same way.

## Affected configuration items

When the CMDB is in use, the **Configuration items** panel on the right links the items a ticket is about: type a name or an identifier and click the item. The panel then lists what a failure of those items affects, so you can see at once which services an incident takes down. See *CMDB (configuration items)*.

## Phone and walk-in requests

For now, ask the person to raise the ticket themselves in **My tickets**, or create it as yourself and mention them in the details. Logging a ticket on behalf of someone else is planned.

## Old tickets

To protect people's data, closed tickets are anonymized after the period your organization chose (3 years by default). They still appear in lists and reports with their number, category and dates, as **Anonymized ticket**, but without the requester, the text, the comments or the files.

