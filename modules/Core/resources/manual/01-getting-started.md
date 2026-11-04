---
title: Getting started
audience: everyone
---

ICT Management Toolkit brings your organization's IT information together in one place. Today it covers **tickets**: you report problems and request services, and the IT team tracks, prioritizes and resolves them.

## Signing in

Open the application and enter your credentials on the sign-in page.

- **Company (Active Directory) account:** use your usual Windows username, for example `jdoe`, `CORP\jdoe` or `jdoe@corp.example`, and your Windows password. Your account is created automatically the first time you sign in, as long as IT has given your group access.
- **Company single sign-on:** when your organization connected its identity provider, click **Sign in with** and its name, under the sign-in form, and sign in there as you do for your other company applications. If the page asks for your work email instead, enter it and click **Continue with single sign-on**. Your account is created the first time.
- **Local account:** use your email address and your password. You choose it yourself, from the invitation email you receive when your account is created.

You cannot use your Windows password on a local account, or a local password on an Active Directory account. Each account has exactly one way of signing in.

**Keep me signed in** keeps a local account signed in on this browser. It never applies to Active Directory accounts: you sign in again when your session ends.

If you use two-factor authentication, you then enter the code from your authenticator app or use your security key; with a **passkey** you can also sign in without your password. See *Your account security*.

**Forgot your password?**, under the sign-in button, emails you a link to choose a new one. It works for local accounts only; Windows (Active Directory) passwords are changed in Windows.

### If you cannot sign in

| Message | What it means |
|---|---|
| *These credentials do not match our records.* | The username or password is wrong, or the account is disabled. The message is the same in every case on purpose, so nobody can find out which accounts exist. |
| *Too many login attempts.* | After 5 failed attempts in a minute, or 20 in 15 minutes for the same account, sign-in is paused for a while. Wait for the time shown and try again. |
| *Your account is not authorized to use this application.* | Your Windows password was right, but none of your Active Directory groups gives you access. Ask IT to add you to the right group. |
| *Active Directory sign-in is temporarily unavailable.* | The application cannot reach the company directory. Try again later; local accounts still work. |
| *Your account is not a member of any active organization.* | You are signed in but have no access yet. Contact your administrator. |
| *This code is not valid.* | The two-factor code is wrong or was already used. Codes change every 30 seconds; wait for the next one. Check that your phone's time is set automatically. |

## Finding your way

The menu at the top shows only what you are allowed to use. Entries marked **▾** open a list; click elsewhere or press Escape to close it.

| Menu entry | Who sees it | What it is for |
|---|---|---|
| **Request a service** | everyone | Order something from the IT service catalog (a laptop, VPN access…). |
| **My tickets** | everyone | Report a problem and follow your requests. |
| **My equipment** | people who have IT equipment or software | The devices IT handed to you, and your software. |
| **My processes** | owners of business processes | The processes you own and their impact analysis. |
| **Ticketing ▾** | IT staff | Work on tickets (**All tickets**), find documented workarounds (**Known errors**), plan changes (**Change calendar**), approve them (**Approvals**, CAB members) and see **Reports** (team leads). |
| **Configuration & assets ▾** | IT staff | What IT runs and how it depends on each other (**CMDB**), the equipment register (**Assets**), software **Licences** and the **Application portfolio** with each application's technical documentation. |
| **My applications** | owners of applications | The applications whose documentation you keep up to date. |
| **My risks** | owners of risks | The risks you assess. |
| **My plans** | owners of continuity plans | The continuity and recovery plans you keep up to date and exercise. |
| **Risk & continuity ▾** | IT staff, continuity coordinators | The **business impact analysis**, **recovery gaps**, **continuity plans** with their exercises and activations, **continuity coverage** and the **risk register**. |
| **Suppliers & contracts ▾** | IT staff | Whom to call at a supplier (**Suppliers**) and, for asset managers and team leads, the **Contracts** with them. |
| **Administration** | administrators, team leads, asset managers | Configure the application. Each person sees only the settings they may change. |

When only one entry of a group is yours, it appears on its own, without the **▾**.

**User manual**, at the top right of every page, opens this manual.

## Find anything

The **Search** box at the top of every page looks through tickets, configuration items, assets, licences, applications, business processes, continuity plans and exercises, risks, controls, findings, contracts, suppliers, racks, projects and documentation. Type at least two letters and press Enter. You only see what you are allowed to open, and each kind shows its first results; to narrow them down, use more words or that module's own list.

## Tags

IT staff can label tickets, configuration items, assets, applications, risks, projects and suppliers with free **tags**, such as `audit-2026` or `end of support`, in the **Tags** box on the record's page, if they may change that record. Click a tag to see everything carrying it that you may open. Tags also show up in the search. Each change is kept in the record's history. Administrators rename or delete tags in **Administration → Tags**.

## Notifications

You receive an email when your ticket is registered, when IT replies, and when it is resolved. Emails contain the ticket number, its title and a link, but never the text of the conversation. Sign in to read it.

## Signing out

Use **Sign out** at the top right. If your account is deactivated, or removed from your organization, you lose access on your next click, even if you were already signed in.
