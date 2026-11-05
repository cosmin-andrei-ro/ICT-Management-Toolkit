# Security model

For operators and reviewers: what the toolkit protects, how, where its limits are, and what you must do yourself. To report a vulnerability, see [SECURITY.md](../SECURITY.md).

This describes the design. It has not been audited or tested by an independent third party. The target is OWASP ASVS level 2, and level 3 for authentication, authorization and secrets; it has not been formally verified ([Standards and compliance](compliance.md)).

## What needs protecting

- **Business records:** assets, risks, audit findings, continuity plans, tickets. Together they are a map of the company's weak points.
- **Credentials and secrets:** passwords, second factors, mailbox passwords, licence keys, single sign-on and webhook secrets, API tokens, and the credentials in the privileged access vault.
- **Personal data:** employees' names and contact details, ticket contents, sign-in history.
- **The audit trail:** it is evidence, so it must be hard to alter.
- **The separation between organizations** in one installation.

## Who could attack it

| Attacker | Goal |
|---|---|
| Someone on the internet, without an account | Sign in, reach internal addresses, overload the service |
| An employee with a low role | See or change what they have no right to |
| An administrator of one organization | Reach other organizations or their accounts |
| Someone sending email to a ticket mailbox | Pretend to be staff, plant malicious content |
| Someone with a stolen password or token | Act as its owner |

An attacker who controls the server, the database or the application key is outside this model: they can read everything.

## Main threats and defences

| Threat | Defence |
|---|---|
| Password guessing | Limits per account and per address, account locked after 10 failures in a row, an email to the person, known-breached passwords refused |
| Stolen password | Two-factor authentication or passkeys, required for IT staff by default; a fresh code for sensitive actions; sessions ended and API tokens revoked when a password is reset |
| Forged single sign-on answers | Signatures checked strictly; each SAML request answered once and only in the browser that made it; OpenID Connect with PKCE and verified tokens; a provider's second factor trusted only for accounts it created; SAML logout messages must be signed |
| Reaching another person's or organization's data | Permission check on every action, deny by default; queries scoped by organization, with tests; an existing account joins another organization only after accepting |
| Approving your own work | Separation of duties: for example, a change's requester, submitter and last plan editor cannot approve it |
| Script injection (XSS) | Escaped output, sanitized Markdown, strict Content Security Policy with nonces. The admin panel's framework needs a looser policy than the rest of the application |
| Forged requests (CSRF) | Tokens on every form, `SameSite` cookies; the API accepts tokens only |
| SQL injection | Query binding only |
| Malicious uploads | Type checked from the content, size limits, new file names, storage outside the web root, download only through an authorized request; optional antivirus scan, refusing files it cannot check |
| Server-side requests to internal systems (SSRF) | Webhook and single sign-on addresses checked, and the connection held to the checked address; internal and special ranges refused; the PDF service refuses private addresses |
| Spoofed email | Staff replies count only when the mail provider's authentication passes for that exact domain; otherwise they are stored as a marked internal note. A mailbox can require this for everyone |
| Mail loops and floods | Automatic replies ignored, limits per sender |
| Secrets leaking | Encrypted at rest with the application key, never logged or audited; licence keys shown only after a fresh code, and that is recorded |
| Hiding tracks | Append-only audit trail, enforced by a database trigger: edits and deletions are refused |
| Data leaving unnoticed | Exports are queued, rate limited, recorded, private to their requester and deleted after 7 days |
| Vulnerable dependencies | `composer audit` and `npm audit` on every CI run; CI actions pinned to exact versions |

## Known limits

- **Antivirus scanning of uploads** is optional and off by default; without it, files are checked by type and size only. Files read by imports are not stored or scanned. Scanning cannot catch what the signatures do not know.
- **Single sign-on logout:** single logout exists for SAML, when the provider's address is set. For OpenID Connect, a session already open here survives the end of the provider's session.
- **Real identity providers:** automated tests use simulated providers. OpenID Connect and SAML, including encrypted assertions and single logout, were also tried by hand against one open-source identity provider in a local setup (2026-11-04); other providers are untested.
- **Older passwords:** passwords shorter than the current minimum stay valid until changed. Someone who stops using a second factor keeps a password that was only allowed with one.
- **Privileged access vault:** its key is separate from `APP_KEY`, but it sits in the environment of the same server. It protects against leaked backups and database access, not against a compromised server. Shown credentials reach the browser. The module has not been independently reviewed; assess it before storing production credentials.
- **Who has an account:** someone allowed to invite people can tell from the lists whether an email already has an account.
- **Item search:** the configuration item pickers in other modules search item names even for people without the right to view the CMDB, because owners must be able to pick items.
- **API rights:** `core.api.use` controls who may issue tokens. A token issued by an operator works for its owner without that right.
- **IMAP connections** rely on certificate checks; they are not held to one checked address.
- **CI action versions** are pinned by hash and do not update on their own, so they need updating by hand.

## What the operator must do

- **Serve over HTTPS only**, and keep the database, Redis and internal services off the internet.
- **Never run the demo data on a reachable server:** its passwords and two-factor secret are public.
- **Protect `.env`.** Back it up: without its keys, stored secrets cannot be read, and anyone holding them can read them. Keep `PAM_KEY` apart from database backups, or the vault's separate key gains nothing.
- **Keep the system updated:** the operating system, PHP, PostgreSQL, and this application.
- **Run and test backups.** Keep them as protected as the live data.
- **Limit administrators,** and keep two-factor authentication required for IT staff.
- **Set retention periods** and review the audit trail and exports, as your own obligations require.

[Installation and operation](installation.md) has the details. Compliance with NIS2, DORA, GDPR or ISO/IEC 27001 also depends on your own processes.
