---
title: Requests about personal data
audience: administrators
---

People have the right to receive the personal data an organization holds about them, in a format they can read and reuse (GDPR articles 15 and 20). The toolkit prepares it for you.

## Exporting a person's data

1. Open **Administration → Access → Personal data**.
2. Choose the person and select **Prepare the export**, then confirm.
3. When the export is ready, it appears under **Your recent exports** with a **Download** link, available for seven days.

The workbook contains:

- **Account:** name, e-mail, type of account, roles, when it was created and last used, whether two-factor authentication is on, and whether the account is locked.
- **One sheet per module** where the person appears. Each lists the records they are part of and how: requester or watcher of tickets, owner of risks, controls or processes, holder of equipment and licences, author of documentation pages, project member, and so on. It also includes the text they wrote, such as the description of their tickets.
- **Comments** and **files** they wrote or uploaded.
- **Activity:** everything recorded in the audit log about what they did, or about their account.

## Good practice

- Check who is asking before preparing the export, for example by calling the person on a known number.
- The workbook contains personal data: give it only to the person, through a safe channel. Do not keep copies.
- Each organization answers for its own data. If the person also belongs to another organization in the toolkit, that organization's administrator exports its part.
- Preparing and downloading the export are both recorded in the audit log.

## Anonymizing a person

When someone asks to be forgotten and the organization no longer has to keep their data, use **Anonymize…** on the same page.

1. Export their data first if they asked for it: afterwards it cannot be told apart.
2. Choose the person, type their e-mail address, and enter a code from your authenticator app.
3. Select **Anonymize**.

What happens:

- **The account:** the name becomes "Former user #…", the e-mail address is removed, and every way to sign in ends. That covers the password, two-factor authentication, passkeys, single sign-on, API tokens and open sessions. The person leaves the organization and their teams.
- **Records:** tickets, risks, audits, projects and the rest stay, with "Former user" where the name was, so their history remains complete. The text the person wrote in them stays, as part of the organization's records.
- **Purely personal data goes:** the sender address of the e-mails they sent to the service desk, the IP address of their equipment handover acknowledgements, and their dashboard preferences and report subscription.
- **The audit log** keeps who did what and when, but their name, e-mail address, IP addresses and browser are removed from it.

Anonymization cannot be undone. It is not possible for:

- yourself;
- the last administrator;
- someone who also belongs to another organization in the toolkit. Each organization must remove them first, because the account is shared.

The anonymization itself is recorded in the audit log, without the old name.

## Data retention

**Administration → Access → Data retention** sets how long the organization keeps each kind of data. Every night, what is older is handled:

- **Audit log:** older entries are deleted. The default is 5 years.
- **Closed tickets:** counted from closing. Older tickets are anonymized. The default is 3 years.
  - They keep their number, category, service, dates and SLA, so reports and statistics stay right.
  - They lose the requester, the title and description, the comments and the files.

Leave a period empty to keep that data forever. Choose periods that match your legal obligations: audits under ISO 27001 or NIS2 usually ask for evidence of the last few years. What each nightly run removed is recorded in the audit log as **Retention applied**.

