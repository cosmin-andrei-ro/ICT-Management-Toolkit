# ADR 0001 — Modular monolith

- **Status:** Accepted
- **Date:** 2026-11-01

## Context

The platform covers many domains: ITSM, CMDB, ITAM, DCIM, BIA, BCM, Risk and Audit. These domains are tightly linked. An incident affects a CI, the CI supports an application, and the application is part of the BIA. The project is open source and self-hosted, and small IT teams must be able to install it easily.

## Decision

We build a **modular monolith** in Laravel. Each module lives in `modules/<Name>/` and follows the layout in `CLAUDE.md` §5.1.

- Each module has its own PSR-4 namespaces, for example `Modules\Ticketing\` → `modules/Ticketing/src/` and `Modules\Ticketing\Database\` → `modules/Ticketing/database/`. The entries are added to `composer.json` when the module is created.
- Each module has a service provider extending `App\Support\Modules\ModuleServiceProvider`. The base class loads the module's migrations, views, translations and routes. `routes/web.php` gets the `web` middleware; `routes/api.php` is mounted under `/api/v1`.
- Modules are enabled in `config/modules.php`. `App\Providers\ModulesServiceProvider` registers only enabled modules and rejects a provider that does not extend the base class.
- A module never touches another module's tables. Modules communicate through public contracts, domain events or Core entities.

## Rejected alternatives

- **Microservices:** the operational cost is too high for self-hosted installs, and distributed transactions would complicate every cross-module link.
- **Third-party module package** (e.g. `nwidart/laravel-modules`): it adds conventions and generators we do not control, for a problem solved in about 100 lines.
- **Classic monolith in `app/`:** the boundaries between domains would erode over time.

## Consequences

- One deployment and one database, so cross-module changes get ACID transactions.
- Module boundaries are enforced by convention and architecture tests, not at runtime.
- Module tests run in the same Pest suite.
