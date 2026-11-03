# Installation and operation

For whoever installs and runs the toolkit on a server. To try it on your own machine, see [Development setup](development/getting-started.md).

## Requirements

| Component | Version | Notes |
|---|---|---|
| [PHP](https://www.php.net) | 8.3 or later (developed and tested on 8.5) | Extensions: `pdo_pgsql`, `redis`, `ctype`, `dom`, `fileinfo`, `filter`, `iconv`, `intl`, `ldap`, `mbstring`, `openssl`, `session`, `tokenizer`, `xmlreader`, `zip` |
| [PostgreSQL](https://www.postgresql.org) | 18 | PostgreSQL-specific features are used (`jsonb`, full-text search, partial indexes, triggers); other databases are not supported. |
| [Redis](https://redis.io) | 7 or later | Cache, sessions and the queue. |
| Web server | [nginx](https://nginx.org) or [Apache](https://httpd.apache.org), with [PHP-FPM](https://www.php.net/manual/en/install.fpm.php) | Document root: `public/`. HTTPS required. |
| [Node.js](https://nodejs.org) | 20 or later | Only to build the front-end assets. |
| Mail server | SMTP for outgoing email; IMAP for email-to-ticket (optional) | |
| Active Directory | Optional | Reachable over LDAPS (port 636). |

## Installation

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
   LOG_LEVEL=warning

   MAIL_HOST=smtp.example.com
   MAIL_PORT=587
   MAIL_USERNAME=…
   MAIL_PASSWORD=…
   MAIL_FROM_ADDRESS=servicedesk@example.com
   ```

   `APP_URL` must be the address people really use: requests for any other host, except its subdomains, are refused, and passkeys are tied to it.

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

   At the first sign-in, the administrator sets up two-factor authentication with an authenticator app (required for every role other than *Requester* until the organization chooses otherwise under **Administration → Access → Security settings**). They then invite people under **Administration → Access → People**, or map Active Directory groups (see below).

5. **Optimize** for production.

   ```bash
   php artisan optimize
   ```

6. **Web server.** Point the document root to `public/` and serve the site over HTTPS only. The application sends strict security headers (CSP, HSTS). `storage/` and `bootstrap/cache/` must be writable by the web server's user.

   Behind a reverse proxy or load balancer that terminates TLS, list it in `.env`, otherwise the application sees every visitor with the proxy's address and believes the connection is plain HTTP:

   ```dotenv
   TRUSTED_PROXIES=10.0.0.5
   ```

   Never list addresses that are not your proxies: anyone sending requests from them could fake their IP address. Sign-in limits count per address, so behind an unlisted proxy all visitors share one limit.

7. **Background work** — see [the next section](#background-work-scheduler-and-queue). Without it, no email is sent or read (invitations and password resets included), SLA clocks do not warn, and exports are never generated.

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
| `projects:report-reminders` | Mondays at 08:30 | Emails project managers the projects whose status report is due |
| `risk:reviews:remind` | daily at 08:00 | Emails risk owners whose risks are due for reassessment |
| `applications:reviews:remind` | daily at 07:45 | Emails application owners whose documentation is due for review |
| `applications:digest` | Mondays at 07:55 | Emails application managers what the portfolio needs |
| `applications:certificates:remind` | daily at 07:40 | Emails application owners about certificates expiring soon |
| `bcm:plans:remind` | daily at 07:35 | Emails plan owners whose plan is due for review or for an exercise |
| `bcm:digest` | Mondays at 07:50 | Emails continuity coordinators what their plans need |
| `ticketing:mail:prune` | daily | Deletes old entries of the incoming email log |
| `core:retention` | daily at 03:30 | Applies each organization's retention periods |
| `audit:tests:remind` | daily at 08:05 | Emails control owners whose control test is due |
| `audit:digest` | Mondays at 08:15 | Emails compliance managers what needs attention |
| `documentation:digest` | Mondays at 08:20 | Emails space owners what needs attention |
| `reporting:snapshot` | daily at 23:30 | Records each day's dashboard figures |
| `reporting:monthly-report` | 1st of the month at 07:00 | Emails the monthly management report |
| `core:directory:sync` | every 15 minutes, when AD is enabled | Blocks disabled AD accounts, refreshes roles |

**The queue worker** sends email, reads mailboxes and generates exports. Run it under a process supervisor, for example [systemd](https://systemd.io) (`/etc/systemd/system/ictmt-queue.service`):

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

PDFs (application documentation, BIA sheets, continuity plans and exercise reports, the risk and compliance reports, Statements of Applicability) are made by [Gotenberg](https://gotenberg.dev), an optional service running [headless Chrome](https://www.chromium.org) (ADR 0030). Without it, the **Print** button and the browser's *Save as PDF* do the same job.

Run it on the internal network only, with JavaScript off and no network access for the documents:

```bash
docker run -d --name gotenberg --restart unless-stopped -p 127.0.0.1:3000:3000 gotenberg/gotenberg:8.39.0 \
  gotenberg --chromium-disable-javascript \
  '--chromium-deny-list=^file:(?!//\/tmp/).*' '--chromium-deny-list=^(https?|ftp|wss?):.*' \
  --chromium-deny-private-ips --libreoffice-disable-routes --api-disable-download-from --webhook-disable
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

5. Sign in as the administrator and map AD groups to roles under **Administration → Access → AD group mappings**. People without a mapped group cannot sign in; a good default is `Domain Users` → *Requester*. Details: user manual, *Access and roles*, and [ADR 0007](adr/0007-active-directory-authentication.md).

## Single sign-on

Providers are set up per organization in the application: user manual, *Access and roles*. SAML needs HTTPS: the cookie that ties the provider's answer to the browser is cross-site, and without HTTPS a provider on another site fails.

## Email

**Outgoing** (notifications): the `MAIL_*` settings of the installation. Ticket emails are sent from `MAIL_FROM_ADDRESS`.

**Incoming** (email-to-ticket), optional:

1. Enable IMAP on the server for the support mailbox (on Exchange: the IMAP4 service and IMAP for that mailbox), and create its credentials.
2. Sign in as the administrator, open **Administration → Service desk → Mailboxes**, add the mailbox (usually port 993 with SSL/TLS), and click **Test connection**.
3. Make sure the scheduler and the queue worker run.

Only people with an account in the organization can raise tickets by email; other senders are ignored. Ticket emails then use the mailbox as their reply address, so replies come back to the ticket. Details: user manual, *Tickets by email* and *Service desk setup*, and [ADR 0015](adr/0015-email-to-ticket.md).

Staff messages by email count as verified only with a DMARC pass, or an SPF pass for the domain of the sender's address; otherwise they stay internal notes. Check a real message's `Authentication-Results` header before relying on this.

Settings, for special cases:

| Variable | Default | Meaning |
|---|---|---|
| `TICKETING_MAIL_ALLOWED_PORTS` | `993,143` | Ports mailboxes may use |
| `TICKETING_MAIL_VERIFY_CERTIFICATES` | `true` | Keep on; add an internal CA to the server's CA bundle instead |
| `TICKETING_MAIL_MAX_PER_SENDER_PER_HOUR` | `20` | Tickets and replies accepted per sender and hour |
| `TICKETING_MAIL_RETENTION_DAYS` | `90` | Days the incoming email log is kept |
| `TICKETING_MAIL_TRUSTED_AUTHSERV_IDS` | empty | Your mail server's name in `Authentication-Results`, comma separated. Empty believes any such header, so the server must remove those senders wrote |

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
| `WEBHOOKS_ALLOW_PRIVATE_NETWORKS` | `false` | Allow webhook receivers on private networks (internal receivers only) |
| `WEBHOOKS_ALLOW_HTTP` | `false` | Allow webhook receivers over plain HTTP |
| `WEBHOOKS_TIMEOUT` | `10` | Seconds to wait for a webhook receiver |
| `AUTH_LOCKOUT_AFTER` | `10` | Consecutive failures that lock a local account; `0` disables |
| `AUTH_CHECK_BREACHED_PASSWORDS` | `true` | Refuse passwords known from breaches (Have I Been Pwned, k-anonymity) |
| `ATTACHMENTS_MAX_KB` | `20480` | Largest attachment, in kilobytes |
| `AUDIT_TEST_REMINDER_DAYS` | `14` | Days before a control's next test when its owner is emailed |
| `PROJECTS_REPORT_EVERY_DAYS` | `14` | Age at which a project's status report is due again |
| `DCIM_POWER_WARN_PERCENT` | `80` | Load of a UPS or PDU that needs attention |
| `REPORTING_ATTACH_PDF` | `true` | Attach the monthly report as a PDF when the PDF service runs |
| `REPORTING_SNAPSHOT_KEEP_DAYS` | `730` | Days of daily figures kept |
| `GOTENBERG_TIMEOUT` | `60` | Seconds to wait for the PDF service |
| `SSO_ALLOW_HTTP` | `false` | Allow identity providers over plain HTTP (development only) |
| `LDAP_ALLOW_INSECURE` | `false` | Allow Active Directory without TLS (development only) |

## Accounts in several organizations

No organization's administrator may reset the second factor of an account that also belongs to another organization, or unlock it. When the person cannot do it themselves, run on the server, after checking that the request really comes from them:

```bash
php artisan core:account:reset-mfa person@example.com
php artisan core:account:unlock person@example.com
```

Both are recorded in the audit trail.

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
