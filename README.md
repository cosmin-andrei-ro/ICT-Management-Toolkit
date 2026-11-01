# ICT Management Toolkit

Open-source platform (AGPL-3.0-or-later) that centralizes a company's IT information in one place: service desk (ITIL 4), CMDB, asset management, datacenter infrastructure, business impact analysis, continuity planning, risk assessment and audit & compliance. Each area is a module, and every module is linked through a shared core.

## Stack

Laravel 13 · PHP 8.5 · PostgreSQL 18 · Redis · Livewire 4 + Filament 5 · Tailwind CSS 4 · Pest · Larastan

## Getting started

PHP and Composer run inside the Laravel Sail containers; only Docker is required on the host.

```bash
cp .env.example .env
docker run --rm -v "$(pwd)":/var/www/html -w /var/www/html laravelsail/php84-composer:latest composer install --ignore-platform-reqs
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install && ./vendor/bin/sail npm run build
```

## Quality checks

```bash
./vendor/bin/sail composer test         # Pest (on PostgreSQL)
./vendor/bin/sail composer analyse      # PHPStan / Larastan, level max
./vendor/bin/sail composer lint         # Laravel Pint (fixes)
./vendor/bin/sail composer lint:check   # Laravel Pint (check only, used in CI)
```

## Documentation

- [Architecture decision records](docs/adr/)
- [Contributor and AI agent guide](CLAUDE.md)

## License

[GNU Affero General Public License v3.0 or later](LICENSE). If you run a modified version as a network service, you must offer its source code to its users.
