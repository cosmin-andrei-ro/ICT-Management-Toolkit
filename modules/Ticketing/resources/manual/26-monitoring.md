---
title: Incidents from monitoring
audience: agents
---

When your monitoring tool (for example Zabbix or Prometheus) detects a problem, it can open an incident here on its own. These incidents show **Monitoring** as their channel and a **Monitoring alert** box on the ticket page.

## What happens

- **The alert starts:**
  - An incident opens with the alert's title and details.
  - Its priority follows the alert's severity: critical alerts are the most urgent.
  - When the alert names a server or another item known to the CMDB, the incident is linked to it, so you can see what else is affected.
- **The alert repeats:** no new incident opens. The box counts the occurrences and shows when the alert was last seen.
- **The alert clears:**
  - If nobody has started working on the incident yet (it is still *New* or *Assigned*), it is resolved automatically, with a note.
  - If you are already working on it, only an internal note is added. You decide whether the problem is really solved.
- **The alert starts again after you closed its incident:** a new incident opens.

## Setting it up

An administrator or team lead prepares the connection:

1. Create a service account with the **agent** role, for example *Monitoring*.
2. While signed in as that account, issue an API token with the **Send monitoring alerts** permission (**Account → API tokens**).
3. Point the monitoring tool at one of these addresses:
   - `/api/v1/monitoring/alertmanager` for Prometheus Alertmanager, with the token as a bearer token. In Alertmanager, a `ci` label can name the CMDB item when the `instance` label does not.
   - `/api/v1/monitoring/alerts` for other tools. They send a small JSON message:
     - `key`: the same for one problem, for example `srv-db-01:disk`;
     - `status`: `firing` or `resolved`;
     - `title` and `severity`: `critical`, `major`, `minor`, `warning` or `info`;
     - `item`: the host name or CMDB identifier.
