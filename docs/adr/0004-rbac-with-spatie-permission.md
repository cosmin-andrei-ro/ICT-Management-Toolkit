# ADR 0004 — RBAC with spatie/laravel-permission, scoped per organization

- **Status:** Accepted
- **Date:** 2026-11-01

## Context

We need granular permissions per module and per action, deny by default, with users holding different roles in different organizations of the same instance (see `CLAUDE.md` §5.3 and §6).

## Decision

- Use `spatie/laravel-permission` (MIT, actively maintained) with **teams** enabled. The team key is `organization_id`, so a user can hold different roles in different organizations.
- The package tables are adapted to UUIDs (`model_morph_key`, and the role and permission keys).
- Permission names follow `<module>.<entity>.<action>`, for example `ticketing.ticket.assign`.
- Permissions are defined in code, in one seeder per module. Default roles are only a starting point and can be edited from the administration panel.
- Checks happen **only through Policies**. Controllers, Livewire components and Filament resources never check roles directly.

## Rejected alternatives

- **Custom implementation:** more security-sensitive code to write and maintain.
- **Bouncer:** less active, with fewer integrations (Filament, documentation).

## Consequences

- The current organization must be set on every request (`setPermissionsTeamId`) before any permission check. This is covered by middleware and isolation tests.
- The permission cache lives in Redis and must be invalidated when roles change. The package does this automatically.
