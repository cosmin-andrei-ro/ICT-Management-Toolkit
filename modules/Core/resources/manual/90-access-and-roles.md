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
| **Agent** | service desk staff | Everything a requester can, plus see and work every ticket of the organization, assign tickets, manage problems, raise changes, keep the CMDB up to date, read continuity plans and application documentation, hand out and take back equipment, give software licence seats, and see suppliers and their contacts. |
| **Team lead** | service desk lead | Everything an agent can, plus see reports, approve changes, configure the service desk (categories, queues, SLA targets, catalog, CAB boards, standard changes), define CMDB item types, keep the asset register and the software licences, read the business impact analysis, write and exercise IT disaster recovery plans, and manage suppliers, contracts and locations; create documentation spaces and read and write every one. These settings are in **Administration**. |
| **Continuity coordinator** | business continuity | Run the business impact analysis and the continuity and recovery plans: processes, plans, their owners, approvals, exercises and recovery tests, activations, coverage and the weekly digest, settings; keep departments. |
| **Asset manager** | IT asset and contract management | Raise tickets; see the CMDB; keep the asset register (assets, categories, disposal) and the software licences, including showing licence keys; manage suppliers, contracts and locations. |
| **Application manager** | application portfolio | Keep the application portfolio: register applications and their owners, write any application's documentation, approve in place of an owner, edit the documentation's dropdown values; read suppliers and contracts. |
| **Security officer** | information security | Read the application portfolio and give the security approval of the applications' documentation; read compliance and the audit log. |
| **Risk manager** | risk management | Keep the risk register: register risks and their owners, assess any risk, set the risk method and the catalog of threats and vulnerabilities; read compliance and suppliers. |
| **Project manager** | projects | Start projects and run any of them: phases, team, tasks; read the directory. |
| **Compliance manager** | compliance, internal audit | Keep compliance: add frameworks and their requirements, decide which apply and why (the Statement of Applicability), register controls and their owners, map them to requirements, add evidence and record tests of any control, plan audits, record findings and verify their correction; read risks, applications, continuity plans, business processes, suppliers, contracts and the audit log. |
| **Auditor** | internal or external auditor | Read compliance - frameworks, applicability, controls, evidence, tests, audits and findings - and the risks, applications, continuity plans, business processes, suppliers and contracts evidence points to, without changing anything. |
| **CAB member** | change advisory board | Raise tickets; approve changes on the boards they sit on; see the CMDB. |
| **Administrator** | IT administrator | Everything, including people, Active Directory mappings, teams and business calendars. |

The default roles are defined by the application and restored on every update. If you need a different combination of permissions, create an organization-specific role.

## People

**Administration → Access → People** lists the people of your organization: their account type, roles, whether they use two-factor authentication, and their last sign-in.

- **Invite** adds someone. A new person receives an email with a link, valid for 3 days, to choose their own password: you never know it. Someone who already has an account (in another organization, or through Active Directory) receives an email asking them to join yours, and joins only when they accept, signed in, within 14 days. The message you see is the same in both cases. Someone who signs in through another organization's single sign-on cannot join: they need a separate account. **Send the invitation again** if the link expired.
- **Edit** changes the name and roles of local accounts. Roles of Active Directory accounts come from the AD group mappings, so they cannot be changed here.
- **Active** turns an account off: the person cannot sign in anymore. It is offered only for people who belong to your organization alone; otherwise **remove** them from it.
- **Remove from organization** takes away their roles and their access to your organization. Their account and their history stay.
- An account that also belongs to another organization is shared with it: you cannot rename it, reset its two-factor authentication or unlock it here. The person unlocks it themselves by resetting their password, and uses a recovery code if they lose their phone.
- **Reset two-factor** is for someone who lost their phone and their recovery codes. Make sure the request really comes from them, for example by calling them. They set it up again at their next sign-in.
- **Locked** shows since when an account has been locked after too many failed sign-ins in a row. **Unlock** lifts the lock. The person can also unlock it themselves by resetting their password. Make sure the request comes from them: the failures may have been someone guessing the password.

You cannot change your own roles, deactivate yourself or remove yourself, so you cannot lock yourself out by mistake. Ask another administrator.

Invitations, role changes, resets and removals are recorded in the audit trail.

## Security settings

**Administration → Access → Security settings** chooses which roles must use two-factor authentication. People holding one of them cannot work until they set up an authenticator app or a security key. Administrators always must. Until you change it, every role except *Requester* must.

The page also shows two settings that apply to the whole installation and are set by whoever runs it (`AUTH_LOCKOUT_AFTER` and `AUTH_CHECK_BREACHED_PASSWORDS`):

- how many failed sign-ins in a row lock an account;
- whether passwords known from breaches are refused.

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

## Single sign-on

People can sign in with their company account at your identity provider - Microsoft Entra ID, Google Workspace, Okta, Keycloak, ADFS or any provider speaking OpenID Connect or SAML 2.0. Each organization connects its own providers in **Administration → Access → Single sign-on**.

### Connecting a provider

1. At the provider, register the toolkit as a **web application** (OpenID Connect, authorization code flow). Your identity administrator gives you its **issuer URL**, a **client ID** and a **client secret**. For Microsoft Entra ID the issuer is `https://login.microsoftonline.com/<tenant id>/v2.0`; for Keycloak `https://<server>/realms/<realm>`.
2. In **Single sign-on**, click **New**, give the name shown on the sign-in button (for example *Microsoft*), the issuer, client ID and secret, and the **email domains** of your people (for example `company.com`). Only these domains sign in through this provider.
3. Edit the provider: copy the **Redirect URI** shown and register it at the provider as the application's redirect (reply) URI.
4. To give roles by group, have the provider send the groups in the ID token (Entra ID: *groups claim*; Keycloak: a *group membership* mapper) and set the claim's name if it is not `groups`.

The client secret is stored encrypted and never shown again; leave it empty when editing to keep it.

### Connecting a SAML 2.0 provider (ADFS, Shibboleth…)

1. In **Single sign-on**, click **New** and choose the protocol **SAML 2.0**. From the identity provider's metadata, enter its **entity ID**, its **single sign-on URL** and its **signing certificate**, and the email domains of your people.
2. Edit the provider: give the identity provider the **service provider metadata URL** shown (or the entity ID and the **reply URL** next to it). In ADFS this is a *relying party trust*; in Entra ID an *enterprise application* with SAML.
3. Have the identity provider send the person's **email** (the usual Microsoft or LDAP attributes are recognised; otherwise name the attribute), their **name**, and their **groups** in the attribute you set.
4. Assertions must be signed with SHA-256 or stronger.

Signing out of the toolkit does not sign out of the identity provider.

### Who gets in, with which role

- **The first time** someone signs in, their account is created in your organization. If an account with their email already exists and belongs to your organization only, it is linked instead. An account shared with another organization is refused - its owner keeps signing in as before.
- **Roles** of accounts created this way come from **Single sign-on group mappings**: *members of this group of the provider get this role*. Enter the group as the provider sends it (an Entra ID group object id, a Keycloak group name…). They are refreshed at every sign-in; without a matching group the person is a *Requester*.
- **Two-factor authentication:** with **Trust the provider's two-factor authentication** on, people the provider asked for a second factor (it says so in the `amr` claim) are not asked again by the toolkit. This applies only to accounts the provider created: an existing account linked by its email keeps its own two-factor authentication. Otherwise those who use the toolkit's two-factor authentication give their code as usual.
- Refused sign-ins - another domain, an account of another organization - are recorded in the audit trail with the reason.

## Business calendars

**Administration → Organization → Business calendars.** A calendar sets your working hours and days off. The default calendar is used to compute SLA deadlines.

- Set the time zone and the hours for each day of the week. Leave a day empty if nobody works.
- Turn on **Romanian public holidays** to skip them automatically, including Orthodox Easter and Pentecost, which move every year.
- Add **extra days off** for company holidays.
- Only one calendar can be the default. Without one, Monday–Friday 09:00–17:00 (Bucharest time) with Romanian public holidays applies.

## API tokens

Other systems (monitoring, scripts) use the REST API at `/api/v1` with a personal token. IT staff create their own, under their name → **API tokens**:

- the token works only in the organization it was created in;
- it can only do what was chosen for it, and never more than its owner may do;
- creating one asks for a code from the authenticator app;
- it is shown once, then only its name, abilities, last use and expiry (at most a year).

**Administration → Access → API tokens** lists every token of the organization, whose it is and when it was last used. **Revoke** stops one at once, for example when its owner leaves or a token may have leaked. Whoever runs the server can still issue tokens from the command line (`php artisan core:api-token`).

## Audit trail

Every change to configuration and tickets is recorded: who, when, which field, the old value and the new value. Sign-ins, failed sign-ins and role changes are recorded too. Nobody can edit or delete these records, not even administrators.
