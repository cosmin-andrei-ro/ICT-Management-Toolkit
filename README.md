# ICT Management Toolkit

Open-source platform (AGPL-3.0-or-later) that centralizes a company's IT information in one place. Each area of IT work is a module, and every module is linked through a shared core: people, organizations, roles, comments, attachments and an audit trail of every change.

## Standards and compliance

> The application is **not certified** and has not been audited by a third party. Regulations such as NIS2, DORA and GDPR apply to **organizations**, not to software: the toolkit gives you the records, workflows and evidence these regulations ask for, but compliance also depends on your own processes. The tables say what is in place today (**Yes**), partly (**Partial**), not yet (**Planned**), or left out on purpose (**Not planned**).

### How the software is built

| Standard or practice | Status | How |
|---|---|---|
| PSR-12 coding style, PSR-4 autoloading | Yes | Laravel Pint (Laravel preset, strict types and strict comparisons), checked in CI |
| Static analysis | Yes | PHPStan / Larastan at the highest level, checked in CI |
| Automated tests | Yes | Pest on PostgreSQL: every action, authorization (including the "no permission" case), validation, and isolation between organizations |
| Dependency vulnerabilities | Yes | `composer audit` and `npm audit` in CI; only licenses compatible with AGPLv3 |
| Architecture decisions | Yes | Recorded as ADRs in [docs/adr](docs/adr/) |
| Licensing | Yes | AGPL-3.0-or-later, SPDX header in every source file, link to the source code in the interface (AGPL §13) |
| Commit history | Yes | Conventional Commits |

### Security

| Standard | Status | What is covered |
|---|---|---|
| OWASP ASVS 4.0 | Partial (design target) | Level 2 as the minimum, level 3 for authentication, authorization and secrets. Not formally verified. |
| OWASP Top 10 | Yes | Authorization on every action and deny by default, query binding only (no SQL concatenation), escaped output, CSRF protection, strict Content Security Policy without `unsafe-eval` outside the admin panel, SSRF guard on mail servers, upload checks by content (not by name), CSV/formula injection neutralized in exports |
| NIST SP 800-63B (passwords and authenticators) | Yes | Passwords of 12–128 characters without composition rules; rate limiting and lockout; time-based one-time codes from an authenticator app, each accepted once; recovery codes stored as hashes; all other sessions end on a password change |
| RFC 6238 (TOTP) | Yes | Two-factor authentication, required for IT staff |
| RFC 3834 (automatic responses) | Yes | Email-to-ticket ignores auto-replies and bounces; outgoing emails are marked as automatic, to prevent mail loops |
| HTTP security headers | Yes | HSTS, CSP with nonces, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, Secure/HttpOnly/SameSite cookies |
| Secrets at rest | Yes | Mailbox passwords and two-factor secrets are encrypted with the application key, never shown again, never logged or audited |
| Audit trail | Yes | Append-only (enforced by a database trigger): every change to business data with old and new values, sign-ins, failed sign-ins, role changes, two-factor events, exports and downloads |
| Single sign-on (OIDC, SAML2) and WebAuthn | Planned | Active Directory (LDAPS) sign-in is available today |

### Service management

| Framework | Status | What is covered |
|---|---|---|
| ITIL 4 | Partial | Incident management, service request management with a service catalog, problem management with a known error database, change enablement with CAB and emergency CAB, service level management (SLA clocks in working time), service configuration management (CMDB with relationships and impact analysis), service desk reporting, IT asset management (hardware and software licences), service continuity management (BIA and continuity plans). |

### Regulations and information security frameworks

| Requirement | Status | How the toolkit helps |
|---|---|---|
| **NIS2** (Directive (EU) 2022/2555), Art. 21 risk-management measures | Partial | Incident handling (21(2)(b)): ticketing with priorities, SLAs, problem management. Access control, multi-factor authentication and asset management (21(2)(i)(j)): the CMDB records what you run and what depends on what. Logging through the audit trail. Change management for secure maintenance (21(2)(e)). Business continuity (21(2)(c)): business impact analysis, approved continuity and recovery plans, and their exercises and recovery tests. Risk analysis (21(2)(a)): risk register with a defined method, treatment and approved acceptance of residual risks. Assessing the effectiveness of the measures (21(2)(f)): controls mapped to each Art. 21 measure, with evidence and periodic tests. **Planned:** supply chain (21(2)(d)). **Not planned:** the notifications to authorities of Art. 23, which you send outside the toolkit. |
| **DORA** (Regulation (EU) 2022/2554), financial entities | Partial | Identification of ICT assets and their dependencies (Art. 8): the CMDB. ICT-related incident management process (Art. 17): detection, logging, follow-up and root cause. ICT change management (Art. 9(4)(e)): approved, planned, reversible changes with post-implementation review of emergency changes. Strong authentication (Art. 9(4)(d)). ICT business continuity policy and response and recovery plans (Art. 11): BIA with RTO/RPO and approved plans; testing of plans and backup restores with measured recovery time (Art. 11(6), 12(2)); supplier and contract register. **Planned:** digital operational resilience testing programme (Art. 24–25), register of information on ICT third-party arrangements in the regulatory format (Art. 28). **Not planned:** classification and reporting of major incidents to authorities (Art. 18–19). |
| **ISO 22301:2019** business continuity | Partial | 8.2.2 business impact analysis (impact over time, MTPD, RTO, RPO, dependencies, approval, review), 8.3 strategies and 8.4 plans (scope, activation, crisis team, communication, runbook), 8.4.4 recovery against requirements, 8.5 exercises with results per step, measured recovery time and lessons learned, 10.1 findings tracked as problems, 8.5 exercise programme with due dates and reminders, 9.1 coverage monitoring, post-incident review of real activations. |
| **ISO/IEC 27001:2022**, clauses 6.1.2–6.1.3, 8.2–8.3 and Annex A | Partial | Information security risk assessment and treatment with defined criteria, risk owners, approved acceptance of residual risks and reassessment (risk register). 5.9 inventory of information and other associated assets (CMDB, asset register), 5.11 return of assets, 5.29–5.30 information security during disruption and ICT readiness for business continuity (BIA, continuity plans), 5.15–5.18 access control and identities, 5.24–5.27 incident management and learning from incidents, 8.5 secure authentication, 8.15 logging, 8.32 change management. Statement of Applicability (6.1.3 d): every Annex A control with its applicability, justification and the organization's controls meeting it. Evidence of controls in operation and periodic control tests (7.5, 9.1), internal audit programme (9.2), nonconformity and corrective action with root cause and verified effectiveness (10.2). |
| **GDPR** (Regulation (EU) 2016/679) | Partial | Data minimization (exports leave requesters out, comment text is never emailed), storage limitation (the incoming email log is kept 90 days, exports 7 days), security of processing (Art. 32: encryption of secrets, access control, audit trail), separation of organizations. Accountability (Art. 5(2)), records of processing (Art. 30), DPIA (Art. 35) and the other obligations IT evidences can be tracked as a framework, with controls, evidence and audits. **Not planned:** export and anonymization of a person's data on request (Art. 15–17, 20), configurable retention for tickets. |

## What it does today

**Core**
- Several organizations (legal entities) in one installation, with their data kept apart.
- Sign-in with local accounts or **Active Directory** (LDAPS). AD groups are mapped to roles, and accounts are re-checked every 15 minutes.
- **Two-factor authentication** with an authenticator app, required for IT staff, plus recovery codes.
- **People management**: invitations to local accounts by email, roles, deactivation. Password reset by email and password change for local accounts.
- Role-based permissions: requester, agent, team lead, CAB member, asset manager, continuity coordinator, application manager, security officer, risk manager, compliance manager, auditor, administrator.
- **Suppliers** with their support contacts, **contracts** with documents, deadlines and email reminders before each notice deadline, and nested **locations** (sites, buildings, rooms).
- Teams, business calendars (working hours, Romanian public holidays), API tokens.
- Append-only **audit trail**: who changed what, when, with old and new values; sign-ins, role changes, exports.
- Administration panel at `/admin`, a REST API at `/api/v1` (OpenAPI in [docs/api/openapi.json](docs/api/openapi.json)), and the **user manual** at `/manual`.

**Service desk (Ticketing, ITIL 4)**
- **Incidents and service requests**: self-service portal, service catalog with request forms, agent desk with queues, assignment, internal notes, attachments and full-text search.
- **SLAs**: response and resolution clocks in working time, with warnings and breach notifications.
- **Problems** and a **known error database**.
- **Changes**: standard, normal and emergency, with CAB and emergency CAB (ECAB) approval, a change calendar, and post-implementation reviews.
- **Email-to-ticket**: tickets and replies by email, from IMAP mailboxes (e.g. Exchange).
- **Reports**: resolution times, SLA compliance, reopen rate, backlog and volume, exported to Excel or CSV.

**CMDB**
- **Configuration items** of extensible types (servers, applications, databases, services…) with their own attributes, and typed **relationships** between them.
- **Impact analysis**: what a failure affects and what an item needs, plus an interactive dependency map.
- Items linked to tickets, so incidents show the services they take down and the CAB sees what a change touches.
- **Import** from CSV or Excel, with preview.

**IT asset management**
- **Hardware assets** of extensible categories, with automatic asset tags, purchase and warranty details, and a life cycle from order to disposal (with data wiping recorded).
- Who has each asset, from when to when; **My equipment** for every employee.
- Assets linked to CMDB items and to the support contracts that cover them; warranty reminders by email.
- **Handover protocols** to print or save as PDF, with the employee's confirmation of receipt in the portal; **QR labels**; **import** from CSV or Excel and **export** of the register.

- **Software licences** per user, per device or site: seats in use against seats bought per product, over-use flagged, keys encrypted and shown only after an authenticator code (audited), expiry reminders.

**Business impact analysis**
- **Business processes** with owners in the business, assessed in an **impact matrix over time** (financial, legal, reputation, operations, customers × 4 hours to 1 month); MTPD derived from the scores, RTO checked against it, RPO, workarounds; recovery tiers.
- Versioned assessments: owners submit, a continuity coordinator approves or returns them (never their own); yearly review.
- **Dependencies** on IT systems (CMDB), suppliers, locations, other processes, key roles and people; **recovery gaps**: the strictest RTO/RPO each system must meet, through CMDB relationships, against the recovery capability IT declared, with suggested criticality.
- Register export to Excel, BIA sheet per process to print or as PDF, review reminders to owners and a weekly digest for coordinators.

**Continuity and recovery (BCM/DRP)**
- **Business continuity and IT disaster recovery plans**, versioned and approved: scope (BIA processes, CMDB systems), scenarios, activation, crisis team, communication, and a **runbook** whose recovery time is compared with the RTO.
- Plan in force to print or as PDF, for offline use during a crisis.
- **Exercises and recovery tests**: the runbook in force step by step with results and times, measured recovery against the RTO, systems confirmed as recovered in the CMDB, findings raised as problems in the service desk, report to print or as PDF.
- **Coverage report**: assessed processes without a plan in force or not brought back within their RTO, plans not approved or overdue for review or exercise; Excel export of the continuity register; reminders to owners and a weekly digest for coordinators.
- **Activation log** for real disruptions: the crisis team is emailed a link to the plan, time to recover is compared with the RTO, lessons are kept.

**Application portfolio**
- Register of applications with business owner, IT / application owner and deputy.
- **Technical documentation sheet** following the company's template chapter by chapter, with editable dropdowns: general information, support and classification; data categories (GDPR, PCI DSS…), functionalities and integrations with a **data flow diagram drawn from them** (plus the team's own diagram and .drawio file); authentication, access procedures, roles and the **role permissions matrix**; network with firewall rules and a **network diagram drawn from them**, hosting and components **linked to the CMDB**, software, databases, middleware, libraries and licences, technical accounts (vault location only); certificates with **expiry reminders**, secrets (vault location only), compliance, logs and alerts; continuity **linked to the BIA processes**, CMDB dependencies and recovery plans; deployment, scheduled jobs, runbook, known issues, decommissioning checklist and glossary.
- Versioned: the first version and major changes are approved by the business owner, the IT owner and a security officer; minor changes are published and logged in the change history.
- **Full document** to print, or **PDF** through the optional Gotenberg service; the application gets its own CMDB item, running on its components, when its documentation comes into force; yearly review reminders, a weekly digest for application managers and an Excel export of the portfolio.

**Risk assessment**
- **Risk register** of risk scenarios linked to the applications, CMDB items, business processes and suppliers they concern, with the threats and vulnerabilities involved from an editable catalog and the controls in place.
- Configurable **5 × 5 method** (level meanings, risk bands, approval threshold, reassessment interval) and a **heat map**; owners assess their risks.
- **Suggested risks** from the other modules - application data and exposure, certificates, unsupported components, recovery gaps, processes without a plan, recurring incidents, expiring licences, contracts and warranties, supplier dependencies, open major nonconformities, requirements without controls - registered as prefilled risks or dismissed with a reason, never created silently.
- **Treatment**: option, plan, **residual risk**, actions with owners and due dates (optionally tracked in the service desk); **acceptance** of the residual risk, approved by a risk manager other than the owner from the threshold; score history and reassessment reminders.
- **Reports**: heat maps before and after treatment, the risk report to print or as PDF, the register in Excel, open risks on each application's page, a weekly digest for risk managers.

**Audit & compliance**
- **Frameworks**: ISO/IEC 27001:2022 Annex A (identifiers with the toolkit's own titles), NIS2, GDPR and DORA, added with all their requirements, and your own frameworks.
- **Applicability** of each requirement with its justification: the Statement of Applicability for ISO/IEC 27001.
- **Controls** with owners and implementation status, each mapped to requirements of several frameworks at once; each framework's coverage follows from them.
- **Evidence** for each control: records of the toolkit (approved changes, exercises and recovery tests, risks, applications, contracts…) and uploaded documents with an expiry date; **periodic control tests** with results, the document they produced and reminders to owners.
- **Audits** (internal and external) and their **findings** - nonconformities, observations, opportunities - also raised from failed control tests; root cause, **corrective actions** (optionally tracked in the service desk) and closure verified by a compliance manager other than the owner.
- **Reports**: the **Statement of Applicability** and the **compliance report** to print or as PDF, everything in Excel, a weekly digest for compliance managers; open major nonconformities and uncovered requirements suggested as risks.

**Planned modules:** datacenter infrastructure, projects.

**Not done yet:** security keys and passkeys (WebAuthn) as a second factor; single sign-on with OIDC or SAML.

## Requirements

| Component | Version | Notes |
|---|---|---|
| PHP | 8.3 or later (developed and tested on 8.5) | Extensions: `pdo_pgsql`, `redis`, `ctype`, `dom`, `fileinfo`, `filter`, `iconv`, `intl`, `ldap`, `mbstring`, `openssl`, `session`, `tokenizer`, `xmlreader`, `zip` |
| PostgreSQL | 18 | PostgreSQL-specific features are used (`jsonb`, full-text search, partial indexes, triggers); other databases are not supported. |
| Redis | 7 or later | Cache, sessions and the queue. |
| Web server | nginx or Apache, with PHP-FPM | Document root: `public/`. HTTPS required. |
| Node.js | 20 or later | Only to build the front-end assets. |
| Mail server | SMTP for outgoing email; IMAP for email-to-ticket (optional) | |
| Active Directory | Optional | Reachable over LDAPS (port 636). |

## Development

PHP and Composer run inside the Laravel Sail containers; only Docker is needed on the host.

```bash
cp .env.example .env
docker run --rm -v "$(pwd)":/var/www/html -w /var/www/html laravelsail/php84-composer:latest composer install --ignore-platform-reqs
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install && ./vendor/bin/sail npm run build
```

Open http://localhost and sign in as `admin@example.com` with the password `password` (demo data, local environment only). You are then asked to set up two-factor authentication with an authenticator app, as every administrator is. Email sent by the application lands in Mailpit at http://localhost:8025.

Optional containers for development:

- **Active Directory** (Samba): `COMPOSE_PROFILES=ad ./vendor/bin/sail up -d` — see [docs/development/active-directory.md](docs/development/active-directory.md).
- **Mail server** (GreenMail, SMTP and IMAP): `COMPOSE_PROFILES=mail ./vendor/bin/sail up -d` — see [docs/development/email-to-ticket.md](docs/development/email-to-ticket.md).

Background work in development: `./vendor/bin/sail artisan schedule:work` and `./vendor/bin/sail artisan queue:work`.

## Installation (production)

The commands below run on the server, in the application directory, as the web server's user (for example `www-data`).

1. **Get the code and its dependencies.**

   ```bash
   git clone https://github.com/cosmin-andrei-ro/ICT-Management-Toolkit.git /var/www/ictmt
   cd /var/www/ictmt
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build
   ```

2. **Configure** — copy `.env.example` to `.env` and set at least:

   ```dotenv
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://ictmt.example.com
   APP_DISPLAY_TIMEZONE=Europe/Bucharest

   DB_HOST=…
   DB_DATABASE=…
   DB_USERNAME=…
   DB_PASSWORD=…

   REDIS_HOST=…
   REDIS_PASSWORD=…

   SESSION_SECURE_COOKIE=true

   MAIL_HOST=smtp.example.com
   MAIL_PORT=587
   MAIL_USERNAME=…
   MAIL_PASSWORD=…
   MAIL_FROM_ADDRESS=servicedesk@example.com
   ```

   Then generate the application key. **Back it up**: it encrypts sessions and stored secrets such as mailbox passwords, which cannot be read without it.

   ```bash
   php artisan key:generate
   ```

3. **Create the database schema and permissions.**

   ```bash
   php artisan migrate --force
   php artisan core:permissions:sync
   ```

4. **Create the first organization and administrator.** The command asks for the organization's name, the administrator's name, email and password (at least 12 characters). It works only once, on a new installation.

   ```bash
   php artisan core:setup
   ```

   At the first sign-in, the administrator sets up two-factor authentication with an authenticator app (required for every role other than *Requester*). They then invite people under **Administration → Access → People**, or map Active Directory groups (see below).

5. **Optimize** for production.

   ```bash
   php artisan optimize
   ```

6. **Web server.** Point the document root to `public/` and serve the site over HTTPS only. The application sends strict security headers (CSP, HSTS). `storage/` and `bootstrap/cache/` must be writable by the web server's user.

   Behind a reverse proxy or load balancer that terminates TLS, list it in `.env`, otherwise the application sees every visitor with the proxy's address and believes the connection is plain HTTP:

   ```dotenv
   TRUSTED_PROXIES=10.0.0.5
   ```

   Never list addresses that are not your proxies: anyone sending requests from them could fake their IP address.

7. **Background work** — see the next section. Without it, no email is sent or read (invitations and password resets included), SLA clocks do not warn, and exports are never generated.

## Background work: scheduler and queue

Two processes must run all the time.

**The scheduler** runs periodic tasks. Add one cron entry for the web server's user:

```cron
* * * * * cd /var/www/ictmt && php artisan schedule:run >> /dev/null 2>&1
```

| Task | When | What |
|---|---|---|
| `ticketing:sla:check` | every minute | SLA warnings and breaches |
| `ticketing:mail:fetch` | every minute | Reads the support mailboxes (through the queue) |
| `ticketing:tickets:auto-close` | hourly | Closes tickets left resolved for 5 days |
| `core:exports:prune` | hourly | Deletes expired exports |
| `core:contracts:remind` | daily at 07:00 | Contract deadline reminders; renews contracts that renew automatically |
| `itam:warranties:remind` | daily at 07:15 | Emails asset managers about warranties ending soon |
| `itam:licenses:remind` | daily at 07:20 | Emails licence managers about licences expiring soon |
| `bia:reviews:remind` | daily at 07:30 | Emails process owners whose BIA review is due |
| `bia:digest` | Mondays at 07:45 | Emails continuity coordinators what is overdue or waiting |
| `risk:suggestions:refresh` | daily at 06:30 | Refreshes the risks suggested by the other modules |
| `risk:digest` | Mondays at 08:10 | Emails risk managers what the register needs |
| `risk:reviews:remind` | daily at 08:00 | Emails risk owners whose risks are due for reassessment |
| `applications:reviews:remind` | daily at 07:45 | Emails application owners whose documentation is due for review |
| `applications:digest` | Mondays at 07:55 | Emails application managers what the portfolio needs |
| `applications:certificates:remind` | daily at 07:40 | Emails application owners about certificates expiring soon |
| `bcm:plans:remind` | daily at 07:35 | Emails plan owners whose plan is due for review or for an exercise |
| `bcm:digest` | Mondays at 07:50 | Emails continuity coordinators what their plans need |
| `ticketing:mail:prune` | daily | Deletes old entries of the incoming email log |
| `core:directory:sync` | every 15 minutes, when AD is enabled | Blocks disabled AD accounts, refreshes roles |

**The queue worker** sends email, reads mailboxes and generates exports. Run it under a process supervisor, for example systemd (`/etc/systemd/system/ictmt-queue.service`):

```ini
[Unit]
Description=ICT Management Toolkit queue worker
After=network.target redis.service postgresql.service

[Service]
User=www-data
WorkingDirectory=/var/www/ictmt
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always

[Install]
WantedBy=multi-user.target
```

```bash
systemctl enable --now ictmt-queue
```

## PDF documents

PDFs (application documentation, BIA sheets, continuity plans and exercise reports, the risk and compliance reports, Statements of Applicability) are made by [Gotenberg](https://gotenberg.dev), an optional service running headless Chrome (ADR 0030). Without it, the **Print** button and the browser's *Save as PDF* do the same job.

Run it on the internal network only, with JavaScript off and no network access for the documents:

```bash
docker run -d --name gotenberg --restart unless-stopped -p 127.0.0.1:3000:3000 gotenberg/gotenberg:8.39.0 \
  gotenberg --chromium-disable-javascript \
  '--chromium-deny-list=^file:(?!//\/tmp/).*' '--chromium-deny-list=^(https?|ftp|wss?):.*' \
  --libreoffice-disable-routes
```

Then set `GOTENBERG_URL=http://127.0.0.1:3000` in `.env`. In development: `COMPOSE_PROFILES=pdf ./vendor/bin/sail up -d` and `GOTENBERG_URL=http://gotenberg:3000`.

## Active Directory sign-in

1. Create a **service account** in AD with read access to users and groups.
2. Make the domain controllers' **CA certificate** available to the server (PEM file). Connections use LDAPS and verify the certificate.
3. Configure `.env`:

   ```dotenv
   LDAP_ENABLED=true
   LDAP_HOSTS=dc1.corp.example,dc2.corp.example
   LDAP_PORT=636
   LDAP_BASE_DN=dc=corp,dc=example
   LDAP_USERNAME=svc-ictmt@corp.example
   LDAP_PASSWORD=…
   LDAP_ENCRYPTION=ldaps
   LDAP_CA_CERT=/etc/ssl/certs/corp-ca.pem
   LDAP_SYNC_SCHEDULE="*/15 * * * *"
   ```

4. Check the connection, and how a user would be mapped:

   ```bash
   php artisan config:clear
   php artisan core:directory:test
   php artisan core:directory:test jdoe
   ```

5. Sign in as the administrator and map AD groups to roles under **Administration → Access → AD group mappings**. People without a mapped group cannot sign in; a good default is `Domain Users` → *Requester*. Details: user manual, *Access and roles*, and [ADR 0007](docs/adr/0007-active-directory-authentication.md).

## Email

**Outgoing** (notifications): the `MAIL_*` settings above. Ticket emails are sent from `MAIL_FROM_ADDRESS`.

**Incoming** (email-to-ticket), optional:

1. Enable IMAP on the server for the support mailbox (on Exchange: the IMAP4 service and IMAP for that mailbox), and create its credentials.
2. Sign in as the administrator, open **Administration → Service desk → Mailboxes**, add the mailbox (usually port 993 with SSL/TLS), and click **Test connection**.
3. Make sure the scheduler and the queue worker run.

Only people with an account in the organization can raise tickets by email; other senders are ignored. Ticket emails then use the mailbox as their reply address, so replies come back to the ticket. Details: user manual, *Tickets by email* and *Service desk setup*, and [ADR 0015](docs/adr/0015-email-to-ticket.md).

Settings, for special cases:

| Variable | Default | Meaning |
|---|---|---|
| `TICKETING_MAIL_ALLOWED_PORTS` | `993,143` | Ports mailboxes may use |
| `TICKETING_MAIL_VERIFY_CERTIFICATES` | `true` | Keep on; add an internal CA to the server's CA bundle instead |
| `TICKETING_MAIL_MAX_PER_SENDER_PER_HOUR` | `20` | Tickets and replies accepted per sender and hour |
| `TICKETING_MAIL_RETENTION_DAYS` | `90` | Days the incoming email log is kept |

## Other settings

| Variable | Default | Meaning |
|---|---|---|
| `APP_DISPLAY_TIMEZONE` | `Europe/Bucharest` | Time zone of dates shown and entered; data is stored in UTC |
| `TRUSTED_PROXIES` | empty | Reverse proxies whose forwarded headers are believed (addresses or CIDR ranges) |
| `APP_SOURCE_URL` | this repository | Link to the source code shown in the interface (AGPL §13); point it to your fork if you modify the code |
| `TICKETING_AUTO_CLOSE_AFTER_DAYS` | `5` | Days a resolved ticket waits before closing; `0` disables |
| `EXPORTS_RETENTION_DAYS` | `7` | Days an export can be downloaded |
| `CONTRACT_REMINDER_DAYS` | `90,30` | Days before a contract's deadline when reminders are emailed |
| `DEFAULT_CURRENCY` | `EUR` | Currency proposed for new contracts and assets (ISO 4217) |
| `ITAM_TAG_PREFIX` | `AST` | Prefix of automatic asset tags (`AST-000001`) |
| `ITAM_WARRANTY_REMINDER_DAYS` | `30` | Days before a warranty ends when asset managers are emailed |
| `ITAM_LICENSE_REMINDER_DAYS` | `60` | Days before a licence expires when licence managers are emailed |
| `BIA_REVIEW_REMINDER_DAYS` | `30` | Days before a process's BIA review when its owner is emailed |
| `BCM_REVIEW_MONTHS` | `12` | Months from a plan's approval to its review |
| `BCM_EXERCISE_MONTHS` | `12` | Months from a plan's last exercise (or approval) to the next one |
| `BCM_REMINDER_DAYS` | `30` | Days before a plan's review or exercise when its owner is emailed |
| `APPLICATIONS_REVIEW_MONTHS` | `12` | Months from an application's documentation coming into force to its review |
| `APPLICATIONS_REVIEW_REMINDER_DAYS` | `30` | Days before an application's documentation review when its owners are emailed |
| `RISK_REMINDER_DAYS` | `30` | Days before a risk's reassessment when its owner is emailed |
| `GOTENBERG_URL` | empty | Address of the optional PDF service (see *PDF documents*); empty = print from the browser |
| `APPLICATIONS_CERTIFICATE_REMINDER_DAYS` | `30` | Days before a certificate of an application expires when its owners are emailed |
| `ATTACHMENTS_DISK` | `attachments` | Storage for attachments (e.g. an S3-compatible disk) |
| `SANCTUM_EXPIRATION` | `129600` | Lifetime of API tokens, in minutes |

## Updating

```bash
cd /var/www/ictmt
php artisan down
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan core:permissions:sync
php artisan optimize
php artisan queue:restart
php artisan up
```

## Backups

Back up together:

- the **PostgreSQL database**;
- **`storage/app/private`**: attachments and exports;
- the **`.env` file**, above all `APP_KEY`. Without it, mailbox passwords cannot be decrypted.

## Quality checks

```bash
./vendor/bin/sail composer test         # Pest (on PostgreSQL)
./vendor/bin/sail composer analyse      # PHPStan / Larastan, level max
./vendor/bin/sail composer lint         # Laravel Pint (fixes)
./vendor/bin/sail composer lint:check   # Laravel Pint (check only, used in CI)
```

## Documentation

- User manual: `/manual` in the running application (sources in `modules/*/resources/manual`)
- Modules: [docs/modules/](docs/modules/)
- [Architecture decision records](docs/adr/)
- [Contributor and AI agent guide](CLAUDE.md)

## License

[GNU Affero General Public License v3.0 or later](LICENSE). If you run a modified version as a network service, you must offer its source code to its users.
