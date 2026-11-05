# Development setup

[PHP](https://www.php.net) and [Composer](https://getcomposer.org) run inside the [Laravel Sail](https://laravel.com/docs/sail) containers; only [Docker](https://www.docker.com) is needed on the host.

```bash
cp .env.example .env
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd)":/var/www/html -w /var/www/html laravelsail/php84-composer:latest composer install --ignore-platform-reqs
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install && ./vendor/bin/sail npm run build
```

Open http://localhost and sign in with one of the demo accounts below. Email sent by the application lands in [Mailpit](https://mailpit.axllent.org) at http://localhost:8025.

## Demo data

`migrate --seed` builds **Demo Logistics**, a fictional company with three months of history: IT support's tickets, problems and changes, the CMDB and its dependencies, equipment and licences, the business impact analysis, documented applications, continuity plans with an exercise and an activation, the risk register, ISO 27001 and NIS2 controls with an internal audit, documentation including help articles for employees, the server and comms rooms (racks, cabling, A/B power, IP plan), four projects at different stages, and 90 days of indicators for the dashboards' trends. It refuses to run outside the local and testing environments.

Every demo account has the password `password`. IT staff accounts also have two-factor authentication set up with the shared demo secret `JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP`: add it to an authenticator app (as a manual key, time-based, 6 digits) to sign in.

| Account | Role |
|---|---|
| `admin@example.com` | Administrator |
| `agent@example.com`, `agent2@example.com` | Agent |
| `teamlead@example.com` | Team lead |
| `cab@example.com` | CAB member |
| `assets@example.com` | Asset manager |
| `continuity@example.com` | Continuity coordinator |
| `applications@example.com` | Application manager |
| `security@example.com` | Security officer |
| `risk@example.com` | Risk manager |
| `compliance@example.com` | Compliance manager |
| `auditor@example.com` | Auditor |
| `projects@example.com` | Project manager |
| `employee@example.com`, `finance@example.com`, `logistics@example.com`, `hr@example.com`, `sales@example.com`, `warehouse@example.com` | Requester (employees; no two-factor) |

The **privileged access** vault needs a key of its own. Run `./vendor/bin/sail artisan pam:key`, put the result in `PAM_KEY` in `.env`, and seed again to get its made-up credentials. Without it, the demo accounts have none.

Never seed demo data on a server people can reach: the passwords and the two-factor secret are public.

## Optional containers

- **Active Directory** ([Samba](https://www.samba.org)): `COMPOSE_PROFILES=ad ./vendor/bin/sail up -d` — see [active-directory.md](active-directory.md).
- **Mail server** ([GreenMail](https://greenmail-mail-test.github.io/greenmail/), SMTP and IMAP): `COMPOSE_PROFILES=mail ./vendor/bin/sail up -d` — see [email-to-ticket.md](email-to-ticket.md).

## Background work

In development: `./vendor/bin/sail artisan schedule:work` and `./vendor/bin/sail artisan queue:work`.

## Quality checks

```bash
./vendor/bin/sail composer test         # Pest (on PostgreSQL)
./vendor/bin/sail composer analyse      # PHPStan / Larastan, level max
./vendor/bin/sail composer lint         # Laravel Pint (fixes)
./vendor/bin/sail composer lint:check   # Laravel Pint (check only, used in CI)
```
