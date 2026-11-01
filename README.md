# ICT Management Toolkit

Open-source platform (AGPL-3.0-or-later) that centralizes a company's IT information in one place. Each area of IT work is a module, and every module is linked through a shared core: people, organizations, roles, comments, attachments and an audit trail of every change.

## What it does today

**Core**
- Several organizations (legal entities) in one installation, with their data kept apart.
- Sign-in with local accounts or **Active Directory** (LDAPS). AD groups are mapped to roles, and accounts are re-checked every 15 minutes.
- **Two-factor authentication** with an authenticator app, required for IT staff, plus recovery codes.
- **People management**: invitations to local accounts by email, roles, deactivation. Password reset by email and password change for local accounts.
- Role-based permissions: requester, agent, team lead, CAB member, administrator.
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

**Planned modules:** CMDB, IT asset management, datacenter infrastructure, application portfolio, business impact analysis, continuity and recovery, risk assessment, audit and compliance, projects.

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
