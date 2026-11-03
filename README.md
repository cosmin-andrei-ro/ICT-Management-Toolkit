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

They share one core: organizations, people and roles, suppliers and contracts, locations, and an append-only audit trail. Sign-in works with local accounts, Active Directory, OpenID Connect or SAML, with two-factor authentication and passkeys. A REST API, signed webhooks and a monitoring endpoint connect it to other systems.

The full list is in [Features](docs/features.md).

## Try it

You need only Docker. PHP, Composer and Node run inside the containers.

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

The toolkit needs PHP 8.3 or later, PostgreSQL 18 (no other database is supported), Redis, a web server with HTTPS, and two background processes: the scheduler and the queue worker. PDF generation through Gotenberg is optional.

[Installation and operation](docs/installation.md) covers installing, Active Directory, email, settings, updating and backups.

## Standards

The toolkit is **not certified** and has not been audited by a third party. It gives you the records, workflows and evidence that NIS2, DORA, GDPR, ISO/IEC 27001 and ISO 22301 ask for, but compliance also depends on your own processes. [Standards and compliance](docs/compliance.md) says what is in place and what is not, requirement by requirement.

## Documentation

- **User manual:** `/manual` in the running application
- **API:** `/docs/api` in the running application (for people with access to the administration panel), or [the OpenAPI file](docs/api/openapi.json)
- **Modules:** [how each one is built](docs/modules/)
- **Decisions:** [architecture decision records](docs/adr/)
- **Contributing:** [the guide for people and AI agents](CLAUDE.md), and [development setup](docs/development/getting-started.md)

## License

[GNU Affero General Public License v3.0 or later](LICENSE). If you run a modified version as a network service, you must offer its source code to its users.
