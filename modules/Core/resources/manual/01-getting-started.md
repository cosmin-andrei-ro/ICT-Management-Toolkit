---
title: Getting started
audience: everyone
---

ICT Management Toolkit brings your organization's IT information together in one place. Today it covers the **service desk**: you report problems and request services, and the IT team tracks, prioritizes and resolves them.

## Signing in

Open the application and enter your credentials on the sign-in page.

- **Company (Active Directory) account:** use your usual Windows username, for example `jdoe`, `CORP\jdoe` or `jdoe@corp.example`, and your Windows password. Your account is created automatically the first time you sign in, as long as IT has given your group access.
- **Local account:** use your email address and the password you were given.

You cannot use your Windows password on a local account, or a local password on an Active Directory account. Each account has exactly one way of signing in.

**Keep me signed in** keeps a local account signed in on this browser. It never applies to Active Directory accounts: you sign in again when your session ends.

### If you cannot sign in

| Message | What it means |
|---|---|
| *These credentials do not match our records.* | The username or password is wrong, or the account is disabled. The message is the same in every case on purpose, so nobody can find out which accounts exist. |
| *Too many login attempts.* | After 5 failed attempts in a minute, or 20 in 15 minutes for the same account, sign-in is paused for a while. Wait for the time shown and try again. |
| *Your account is not authorized to use this application.* | Your Windows password was right, but none of your Active Directory groups gives you access. Ask IT to add you to the right group. |
| *Active Directory sign-in is temporarily unavailable.* | The application cannot reach the company directory. Try again later; local accounts still work. |
| *Your account is not a member of any active organization.* | You are signed in but have no access yet. Contact your administrator. |

## Finding your way

The menu at the top shows only what you are allowed to use:

| Menu entry | Who sees it | What it is for |
|---|---|---|
| **Request a service** | everyone | Order something from the IT service catalog (a laptop, VPN access…). |
| **My tickets** | everyone | Report a problem and follow your requests. |
| **Service desk** | IT staff | Work on tickets. |
| **Known errors** | IT staff | Find documented workarounds. |
| **Administration** | administrators | Configure the application. |

**User manual**, at the top right of every page, opens this manual.

## Notifications

You receive an email when your ticket is registered, when IT replies, and when it is resolved. Emails contain the ticket number, its title and a link, but never the text of the conversation. Sign in to read it.

## Signing out

Use **Sign out** at the top right. If your account is deactivated, or removed from your organization, you lose access on your next click, even if you were already signed in.
