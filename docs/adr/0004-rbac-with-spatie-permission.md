# ADR 0004 — RBAC with spatie/laravel-permission, scoped per organization

- **Status:** Accepted
- **Date:** 2026-11-01

## Context

We need granular permissions per module and per action, deny by default, with users holding different roles in different organizations of the same instance (see `CLAUDE.md` §5.3 and §6).

## Decision

- Use `spatie/laravel-permission` (MIT, actively maintained) with **teams** enabled. The team key is `organization_id`, so a user can hold different roles in different organizations.
- The package tables are prefixed (`core_roles`, `core_permissions`, `core_model_has_roles`…) and use UUID keys, with real foreign keys to `core_organizations`.
- Roles are either **global** (`organization_id` NULL, the code-defined defaults in `DefaultRole`) or **organization-specific**. A global role must be created with an explicit `organization_id => null`; otherwise the current organization is required, so a missing value can never produce a role shared by every organization.
- Permission names follow `<module>.<entity>.<action>`, for example `ticketing.ticket.assign`.
- Permissions are defined in code: each module registers them, and grants to default roles, in `PermissionRegistry` from its service provider. `php artisan core:permissions:sync` writes them to the database on every deployment. It applies only differences, resets default roles to their code-defined permissions, and reports (but never deletes) permissions that are no longer defined.
- Organizations that need different permission sets create their own roles; global roles are not editable from the UI.
- Role and permission assignments are recorded in the audit log from the package's events (`role_assigned`, `role_revoked`, `permission_granted`, `permission_revoked`).
- Checks happen **only through Policies**. Controllers, Livewire components and Filament resources never check roles directly.

## Rejected alternatives

- **Custom implementation:** more security-sensitive code to write and maintain.
- **Bouncer:** less active, with fewer integrations (Filament, documentation).

## Consequences

- The current organization must be set on every request (`setPermissionsTeamId`) before any permission check. This is covered by middleware and isolation tests.
- The permission cache lives in Redis and must be invalidated when roles change. The package does this automatically.
