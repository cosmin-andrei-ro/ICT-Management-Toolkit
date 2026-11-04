# ADR 0003 — Livewire + Blade for the application, Filament for administration

- **Status:** Accepted
- **Date:** 2026-11-01

## Context

The UI has two kinds of screens:

1. **Working screens** (self-service portal, agent console, CAB approvals, dashboards). They have specific flows and must be clear for busy users.
2. **Configuration screens** (categories, queues, SLA policies, priority matrix, roles). These are repetitive CRUD.

## Decision

- **Livewire 4 + Blade + Alpine.js + Tailwind CSS 4** for the working screens.
- **Filament 5** for the administration and configuration panel. Access to the panel goes through the same Policies and permissions as the rest of the application (`canAccessPanel` plus Policies on resources).
- The application is English only. UI labels still go through translation files (`lang/en`, `__()`) rather than being hardcoded, so localization stays possible later without refactoring.

All packages are MIT licensed, which is compatible with AGPLv3.

## Rejected alternatives

- **SPA (Vue/React + Inertia):** a second build and test ecosystem is too costly for a small team, and Livewire covers the interactivity we need.
- **Filament only:** too rigid for the self-service portal and the ticket flows.
- **Livewire only:** a lot of hand-written CRUD for configuration screens.

## Consequences

- There are two UI layers, and authorization must be tested in both.
- Major Filament and Livewire upgrades have to be tracked together.
