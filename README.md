# ICT Management Toolkit

One place for everything a company's IT department needs to know and do: tickets, assets, applications, risks, continuity plans, audits and documentation, all linked to each other. Self-hosted and open source (AGPL-3.0-or-later).

An incident links to the server it took down, the server to the application it runs, the application to the business process that depends on it, the process to its continuity plan, and the risks and audit controls to all of them. Work done in one module becomes evidence in another.

## Modules

| Module | What it covers |
|---|---|
| **Service desk** | Incidents, service requests, problems and changes (ITIL 4), with SLAs, a self-service portal, a change advisory board and tickets by email |
| **CMDB** | Configuration items and their relationships, impact analysis, a dependency map |
| **Assets** | Hardware life cycle, handovers, QR labels, software licences and their compliance |
| **Data centre** | Racks, cabling, power chains, IP addresses and VLANs |
| **Applications** | The portfolio, with a versioned and approved documentation sheet for each application |
| **Business impact analysis** | Business processes, impact over time, RTO and RPO, recovery gaps |
| **Continuity** | Continuity and recovery plans, runbooks, exercises, activation log |
| **Risk** | Risk register with a configurable method, heat maps, treatment, risks suggested by the other modules |
| **Audit & compliance** | ISO/IEC 27001, NIS2, GDPR and DORA requirements, controls, evidence, audits and findings |
| **Documentation** | Versioned pages, approval for controlled spaces, help articles for employees |
| **Projects** | Phases, task boards, sponsor gates, status reports, budget |
| **Reporting** | Dashboards by role, trends, a monthly management report |

They share one core: several organizations in one installation with their data kept apart, people and roles, suppliers and contracts, locations, comments and attachments.

The full list is in [Features](docs/features.md).

## Security

- **Sign-in:** local accounts, Active Directory, OpenID Connect or SAML, with two-factor authentication required for IT staff, security keys and passkeys
- **Access:** permissions checked on every action, 13 default roles, separation of duties on approvals
- **Traceability:** an append-only audit trail of every change, sign-in and export, with old and new values
- **Protection of data:** secrets encrypted at rest, uploads checked by content, a strict Content Security Policy
- **Personal data:** export and anonymization on request, retention periods per organization

## Integrations

- **REST API** at `/api/v1`, with tokens limited to chosen abilities
- **Signed webhooks** for events from tickets, changes, the CMDB, assets, risks, findings and continuity plans
- **Monitoring alerts** that open and resolve incidents, in the toolkit's own format or from [Prometheus Alertmanager](https://prometheus.io/docs/alerting/latest/alertmanager/)
- **Email to ticket** from IMAP mailboxes, and **imports** from CSV or Excel

## Try it

You need only [Docker](https://www.docker.com). [PHP](https://www.php.net), [Composer](https://getcomposer.org) and [Node](https://nodejs.org) run inside the containers.

```bash
git clone https://github.com/cosmin-andrei-ro/ICT-Management-Toolkit.git
cd ICT-Management-Toolkit
cp .env.example .env
docker run --rm -v "$(pwd)":/var/www/html -w /var/www/html laravelsail/php84-composer:latest composer install --ignore-platform-reqs
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install && ./vendor/bin/sail npm run build
```

Open http://localhost. The demo data is a fictional company with three months of history. Every account has the password `password`:

- `employee@example.com` sees what an employee sees, and signs in directly.
- `admin@example.com` sees everything, and needs a code from an authenticator app: add the demo key `JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP` manually (time-based, 6 digits).

The other roles and the development tools are in [Development setup](docs/development/getting-started.md). These passwords and that key are public, so never seed demo data on a server people can reach.

## Run it

| Needs | Version |
|---|---|
| [PHP](https://www.php.net) | 8.3 or later |
| [PostgreSQL](https://www.postgresql.org) | 18 (no other database is supported) |
| [Redis](https://redis.io) | 7 or later |
| [nginx](https://nginx.org) or [Apache](https://httpd.apache.org) with [PHP-FPM](https://www.php.net/manual/en/install.fpm.php) | HTTPS required |
| [Node.js](https://nodejs.org) | 20 or later, only to build the assets |
| Mail server | SMTP; IMAP only for email to ticket |
| Optional | Active Directory, [Gotenberg](https://gotenberg.dev) for PDFs |

In short, after setting `.env`:

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan key:generate
php artisan migrate --force
php artisan core:permissions:sync
php artisan core:setup      # the first organization and administrator
php artisan optimize
```

Two processes must also run all the time: the scheduler and the queue worker. Without them no email is sent and exports are never generated.

[Installation and operation](docs/installation.md) has every step, plus Active Directory, email, settings, updating and backups.

## Standards

The toolkit is **not certified** and has not been audited by a third party. It gives you the records, workflows and evidence that NIS2, DORA, GDPR, ISO/IEC 27001 and ISO 22301 ask for, but compliance also depends on your own processes. [Standards and compliance](docs/compliance.md) says what is in place and what is not, requirement by requirement. Single logout (SAML) is not done yet.

## Documentation

- **User manual:** `/manual` in the running application
- **API:** `/docs/api` in the running application (for people with access to the administration panel), or [the OpenAPI file](docs/api/openapi.json)
- **Modules:** [how each one is built](docs/modules/)
- **Decisions:** [architecture decision records](docs/adr/)
- **Contributing:** [the guide for people and AI agents](CLAUDE.md), and [development setup](docs/development/getting-started.md)

## Built with

- **Application:** [Laravel](https://laravel.com), [Livewire](https://livewire.laravel.com), [Filament](https://filamentphp.com), [Alpine.js](https://alpinejs.dev), [Tailwind CSS](https://tailwindcss.com), [Vite](https://vite.dev), [Cytoscape.js](https://js.cytoscape.org)
- **Data:** [PostgreSQL](https://www.postgresql.org), [Redis](https://redis.io)
- **Sign-in and API:** [Sanctum](https://laravel.com/docs/sanctum), [laravel-permission](https://spatie.be/docs/laravel-permission), [LdapRecord](https://ldaprecord.com), [php-saml](https://github.com/SAML-Toolkits/php-saml), [WebAuthn Framework](https://github.com/web-auth/webauthn-framework), [JWT Framework](https://web-token.spomky-labs.com), [Scramble](https://scramble.dedoc.co)
- **Documents and mail:** [Gotenberg](https://gotenberg.dev), [OpenSpout](https://github.com/openspout/openspout), [php-qrcode](https://github.com/chillerlan/php-qrcode), [ImapEngine](https://github.com/Directorytree/ImapEngine)
- **Development:** [Laravel Sail](https://laravel.com/docs/sail), [Pest](https://pestphp.com), [PHPUnit](https://phpunit.de), [Mockery](https://github.com/mockery/mockery), [Faker](https://fakerphp.org), [PHPStan](https://phpstan.org), [Larastan](https://github.com/larastan/larastan), [Laravel Pint](https://laravel.com/docs/pint), [Laravel Boost](https://laravel.com/docs/boost), [Pail](https://github.com/laravel/pail), [Pao](https://github.com/laravel/pao), [Tinker](https://github.com/laravel/tinker), [Collision](https://github.com/nunomaduro/collision), [concurrently](https://github.com/open-cli-tools/concurrently)

## License

[GNU Affero General Public License v3.0 or later](LICENSE). If you run a modified version as a network service, you must offer its source code to its users.
