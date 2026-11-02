---
title: Service desk reports
audience: agents
---

**Service desk → Reports** in the menu shows how the service desk is doing. It is available to team leads and administrators. It counts incidents and service requests; problems and changes are left out.

## Choosing the period

Pick the first and the last day. Days are counted in the organization's time zone, and a period can be up to a year. The page opens on the last 30 days.

## Indicators

| Indicator | What it measures |
|---|---|
| **Created** | Tickets raised in the period. |
| **Resolved** | Tickets resolved in the period, whenever they were raised. |
| **Average / median time to resolve** | Hours from creation to resolution, for the tickets resolved in the period. These are calendar hours, nights and weekends included. The median is less affected by a few very old tickets. |
| **Reopened after resolution** | The share of tickets resolved in the period that were reopened in the period. A high value means tickets are closed too early. |
| **SLA met** | For first response and for resolution, the share of SLA clocks that finished on time. Clocks of cancelled tickets do not count. |

Below them:

- **Open now, by queue:** how many tickets wait in each queue today, and how old the oldest one is. This does not depend on the period.
- **Created in the period, by category:** where the work comes from.

## Exporting

**Export to Excel** or **Export to CSV** prepares a file with one row per ticket created in the period: number, type, title, status, priority, queue, category, assignee, channel, dates, hours to resolve and SLA results. The Excel file has a second sheet with the indicators. Requesters are left out of the file.

The file is prepared in the background and appears under the buttons when ready. Only you can download it, for 7 days; then it is deleted. Every export and every download is recorded in the audit trail.
