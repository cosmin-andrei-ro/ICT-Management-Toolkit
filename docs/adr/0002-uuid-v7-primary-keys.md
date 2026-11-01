# ADR 0002 — UUIDv7 primary keys

- **Status:** Accepted
- **Date:** 2026-11-01

## Context

Entities are exposed through URLs, the API and exports, and are linked polymorphically (comments, attachments, audit log, generic links). Sequential IDs can be enumerated, which makes IDOR easier to exploit, and they complicate imports and merging data between instances.

## Decision

All business tables use a **UUID** primary key, stored as PostgreSQL `uuid`. Values are generated in the application by `Illuminate\Database\Eloquent\Concerns\HasUuids`, which produces time-ordered UUIDs (v7, via `Str::uuid7()`). Polymorphic columns use `uuidMorphs`.

The skeleton `users` table (bigint) is converted to UUID in Stage 1. There is no production data yet.

## Rejected alternatives

- **Auto-increment bigint:** enumerable, and it does not work with mixed polymorphic relations.
- **UUIDv4:** random, so it fragments B-tree indexes and slows down inserts.
- **ULID as a string:** 26 characters of text versus 16 native bytes for `uuid`.

## Consequences

- Keys take 16 bytes instead of 8. This is an acceptable cost.
- Human-readable numbers (e.g. `INC-000123`) are separate columns, not primary keys.
