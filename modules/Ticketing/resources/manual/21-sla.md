---
title: Service levels (SLA)
audience: agents
---

Every incident and service request has two clocks:

- **First response:** time until IT support first replies publicly.
- **Resolution:** time until the ticket is resolved.

The clocks count **working time** only, using your organization's business calendar. By default that is Monday–Friday 09:00–17:00 (Bucharest time), without Romanian public holidays.

## Default targets

| Priority | First response | Resolution |
|---|---|---|
| Critical | 15 minutes | 4 working hours |
| High | 1 working hour | 8 working hours (1 day) |
| Medium | 4 working hours | 3 working days |
| Low | 8 working hours (1 day) | 5 working days |

Administrators can change these targets for your organization.

## How the clocks behave

| Situation | Clocks |
|---|---|
| Ticket created | Both start. |
| Status *Pending* (waiting for the requester) | Both pause. |
| Requester replies, or you move it out of *Pending* | Both resume. |
| First public reply from IT support | First response stops. |
| Ticket resolved | Both stop: *Met* or *Missed*. |
| Ticket reopened | Resolution resumes, with the time already used. |
| Ticket cancelled | Both stop as *Not applicable* (never counted as missed). |
| Priority changed during triage | Deadlines are recalculated with the time already used. |

## Warnings

- At **75 %** of a target, the assignee and the queue's team lead get an email.
- When a target is **missed**, they get another one.
- If nobody is assigned, the whole queue team is warned.

Each warning is sent once per clock. Use the **SLA** filter on the **All tickets** page to see what needs attention now.

## Team targets (OLA)

An **OLA** is a target inside IT: how long a ticket may stay with one team, whatever the priority. When a team has one, the ticket's **Service levels** box shows it next to the SLA clocks. It runs in working time and stops while waiting for the requester. It ends when the ticket moves to another team or is resolved, and the next team's clock starts.

When the target is missed, the team's lead gets an email. **Reports** shows how often each team met its target.

## Escalation

A queue can name an **escalation contact**. If an SLA or team target was missed and the ticket is still open after the queue's delay, that person gets one email per missed target. Nothing moves: the ticket keeps its queue and its assignee, so it stays clear who owns it.

