---
title: Access, roles and Active Directory
audience: administrators
---

This chapter is for administrators. Configuration happens in **Administration**, at the top right of the menu. Team leads, asset managers and continuity coordinators open it too, and see only the settings they may change.

## Roles

Each person can have different roles in different organizations. Every role is a set of permissions, and every page and action checks them.

| Role | Typical person | What they can do |
|---|---|---|
| **Requester** | every employee | Raise tickets, request services, follow their own tickets. |
| **Agent** | service desk staff | Everything a requester can, plus see and work every ticket of the organization, assign tickets, manage problems, raise changes, keep the CMDB up to date, read continuity plans, hand out and take back equipment, give software licence seats, and see suppliers and their contacts. |
| **Team lead** | service desk lead | Everything an agent can, plus see reports, approve changes, configure the service desk (categories, queues, SLA targets, catalog, CAB boards, standard changes), define CMDB item types, keep the asset register and the software licences, read the business impact analysis, write IT disaster recovery plans, and manage suppliers, contracts and locations. These settings are in **Administration**. |
| **Continuity coordinator** | business continuity | Run the business impact analysis and the continuity and recovery plans: processes, plans, their owners, approvals, settings; keep departments. |
| **Asset manager** | IT asset and contract management | Raise tickets; see the CMDB; keep the asset register (assets, categories, disposal) and the software licences, including showing licence keys; manage suppliers, contracts and locations. |
| **CAB member** | change advisory board | Raise tickets; approve changes on the boards they sit on; see the CMDB. |
| **Administrator** | IT administrator | Everything, including people, Active Directory mappings, teams and business calendars. |

The default roles are defined by the application and restored on every update. If you need a different combination of permissions, create an organization-specific role.

## People

**Administration → Access → People** lists the people of your organization: their account type, roles, whether they use two-factor authentication, and their last sign-in.

- **Invite** adds someone. A new person receives an email with a link, valid for 3 days, to choose their own password: you never know it. Someone who already has an account (in another organization, or through Active Directory) is added to yours without an email. **Send the invitation again** if the link expired.
- **Edit** changes the name and roles of local accounts. Roles of Active Directory accounts come from the AD group mappings, so they cannot be changed here.
- **Active** turns an account off: the person cannot sign in anymore. It is offered only for people who belong to your organization alone; otherwise **remove** them from it.
- **Remove from organization** takes away their roles and their access to your organization. Their account and their history stay.
- **Reset two-factor** is for someone who lost their phone and their recovery codes. Make sure the request really comes from them, for example by calling them. They set it up again at their next sign-in.

You cannot change your own roles, deactivate yourself or remove yourself, so you cannot lock yourself out by mistake. Ask another administrator.

Invitations, role changes, resets and removals are recorded in the audit trail.

## Teams

**Administration → Access → Teams.** A team groups people who work together, such as "Service Desk" or "Network". Service desk queues are routed to teams, and a ticket in a queue can only be assigned to members of that queue's team. Only members of the organization can join a team.

## Active Directory sign-in

People can sign in with their Windows account when your installation is connected to Active Directory. The technical setup (servers, service account, certificate) is done by whoever installs the application, through environment variables. As an administrator you decide **who gets which role**.

### Mapping AD groups to roles

**Administration → Access → AD group mappings.** Each mapping says: *members of this AD group get this role in this organization*.

1. Enter the group's distinguished name, for example `CN=IT-ServiceDesk,OU=Groups,DC=corp,DC=example`. Your AD administrator can give it to you.
2. Choose the role.
3. Save.

Things to know:

- **Nested groups count.** If `IT-L2` is a member of `IT-ServiceDesk`, members of `IT-L2` get the `IT-ServiceDesk` role.
- **Active Directory decides.** For AD accounts, organizations and roles come only from these mappings, and are refreshed at every sign-in. A role you give an AD user by hand disappears at their next sign-in.
- **People without a mapped group cannot sign in.** A good default is to map a group such as `Domain Users` to *Requester*.
- **Changes in AD reach the application within 15 minutes.** Accounts disabled or deleted in AD are blocked, and group changes are applied. When AD re-enables an account, access returns on its own.
- An AD account whose email already belongs to a local account is refused, to protect the local account. Rename or remove one of the two.

## Business calendars

**Administration → Organization → Business calendars.** A calendar sets your working hours and days off. The default calendar is used to compute SLA deadlines.

- Set the time zone and the hours for each day of the week. Leave a day empty if nobody works.
- Turn on **Romanian public holidays** to skip them automatically, including Orthodox Easter and Pentecost, which move every year.
- Add **extra days off** for company holidays.
- Only one calendar can be the default. Without one, Monday–Friday 09:00–17:00 (Bucharest time) with Romanian public holidays applies.

## API tokens

Other systems (monitoring, scripts) can use the REST API at `/api/v1` with a personal access token. Ask whoever runs the server to issue one:

```
php artisan core:api-token jane@example.com "Monitoring" --ability=tickets:write
```

The token acts as that person: it can never do more than they can. Tokens expire after 90 days by default. The token is shown once; store it in a password manager.

## Audit trail

Every change to configuration and tickets is recorded: who, when, which field, the old value and the new value. Sign-ins, failed sign-ins and role changes are recorded too. Nobody can edit or delete these records, not even administrators.
