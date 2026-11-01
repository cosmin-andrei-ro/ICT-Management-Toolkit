# ADR 0016 — Exports

- **Status:** Accepted
- **Date:** 2026-11-08

## Context

Reports must be exportable to CSV and XLSX (CLAUDE.md §3, Reporting). An export is a copy of business data leaving the application: it has to be authorized, traceable and short-lived. Large exports must not block a web request. Several modules will offer exports (Ticketing now, Reporting, Risk and Audit later).

## Decision

- **A generic mechanism in Core** (`core_exports`):
  - modules register export types in `ExportRegistry`, each with an `ExportSource` (sheets of rows and a file name) and the permission needed;
  - `RequestExport` checks the permission, records the request in the audit trail (`exported`), and queues `GenerateExport` after commit.
- **Generation** runs in the queue, in the export's organization, and writes the file to the private `exports` disk. It uses **OpenSpout** (MIT), which streams row by row and is already installed through Filament. Exports are declared as a direct dependency, pinned to the major version Filament uses.
- **Download** (`/exports/{id}`) is allowed only to the person who asked, while they still hold the type's permission, before expiry (7 days, `EXPORTS_RETENTION_DAYS`), in the export's organization. Each download is audited (`export_downloaded`). Files are always attachments, with `nosniff` and a sandbox CSP.
- **Formula injection** (CSV injection): text cells starting with `=`, `+`, `-`, `@` or a control character are prefixed with `'`. Numbers are written as numbers, so negative values stay numeric.
- **Retention:** `core:exports:prune` (hourly) deletes expired exports, file and record, and requests that failed or got stuck for a day.
- **Data minimization:** sources export what the measurement needs. The ticket export leaves requesters out.

## Alternatives considered

- **Streaming the file in the web request:** simpler, but long requests time out and nothing records what left the application.
- **Filament's export actions:** tied to the admin panel's tables and users; the service desk screens are Livewire pages outside it.

## Consequences

- New modules get exports by registering a source; download, retention and audit come for free.
- Exports wait for the queue worker, so the queue must run in production (it already does for email and notifications).
