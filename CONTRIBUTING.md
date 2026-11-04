# Contributing

Thank you for helping. This page covers the practical steps; [CLAUDE.md](CLAUDE.md) holds the full rules for architecture and code, for people and AI agents alike.

Found a security problem? Do not open an issue: see [SECURITY.md](SECURITY.md).

## Before you start

For anything bigger than a small fix, open an issue first, so we can agree on the approach before you spend time on it. Read the [decisions already taken](docs/adr/) in the area you want to change.

## Set up

Follow [Development setup](docs/development/getting-started.md). PHP, Composer and Node run inside Docker, so every command starts with `./vendor/bin/sail`.

## Rules that matter most

- **One module, one concern.** A module does not read another module's tables. It uses Core, public contracts or domain events.
- **Authorize and validate every action.** Each new endpoint has a test for the allowed case, for invalid input, and for a user without the right (403).
- **Keep organizations apart.** Queries on business data are scoped by organization, and tests prove it.
- **Record changes.** New business entities have an audit trail.
- **No text in the code.** UI text goes through `lang/en` files.
- **No secrets or real data** in code, tests, seeders or commits. Use made-up names and `.example` domains.
- **No product or brand names** in UI text, examples, demo data or screenshots, unless a feature really connects to that product.
- **Migrations** are reversible and safe on large tables. Never change one that may have run in production; add a new one.
- **A bug fix comes with a test** that would have caught it.

Every source file starts with this header:

```php
<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);
```

## Dependencies

Before adding a Composer or npm package, check that its license is compatible with the AGPL, that it is actively maintained, and that we cannot do the job with what we already have. Explain this in the pull request.

## Before you open a pull request

```bash
./vendor/bin/sail bin pint --dirty   # code style
./vendor/bin/sail composer analyse   # PHPStan
./vendor/bin/sail composer test      # tests, on PostgreSQL
```

Your change is done when:

1. the code, migrations and tests are in, and all checks pass;
2. authorization and validation are covered;
3. UI labels are in `lang/en`;
4. the docs match: the module's page, the API description, a new decision record if you made an architecture decision, and the [user manual](docs/adr/0013-user-manual.md) for anything users can see;
5. new entities have an audit trail.

## Pull requests

- Keep each one to a single coherent change.
- Write commit messages in English, as [Conventional Commits](https://www.conventionalcommits.org): `feat(cmdb): ...`, `fix(ticketing): ...`.
- In the description, say what changed and why, how you tested it, and how someone could abuse the new code and what stops them.

## License

By contributing, you agree that your work is published under the project's license, the [GNU Affero General Public License, version 3 or later](LICENSE). Only submit code you have the right to share under it, and do not copy code from sources with an unknown or incompatible license.
