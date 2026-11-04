# ADR 0022 — Software licences

- **Status:** Accepted
- **Date:** 2026-11-14

## Context

Software is the other half of IT asset management: what the organization pays for, how many seats it has, who or what uses them, and when it must renew. Auditors and publishers ask for compliance (seats used against seats bought), and licence keys are secrets that leak easily from spreadsheets.

## Decision

- **Products and licences.** A *software product* (`itam_software_products`) is what licences are bought for; a *licence* (`itam_licenses`) is one purchase: counted **per user**, **per device**, or a **site** licence without a count, with supplier, contract, cost, validity and documents. Products are created with their first licence (typing a new name) and kept tidy in the administration panel.
- **Seats** (`itam_license_assignments`) are given to a person (per-user and site licences) or to a device, an asset (per-device licences); the database allows exactly one of the two and each holder once per licence. A licence with seats cannot switch between people and devices. Disposing of an asset releases its seats.
- **Over-use is recorded, not refused.** The register must show what is really installed; the compliance view counts, per product, seats bought on licences valid today against seats in use on all its licences, and flags the excess. Expired licences buy nothing, so their remaining seats appear as excess until renewed or released.
- **Keys** are encrypted at rest (`encrypted` cast with the application key) and hidden on the model, so they never appear in serialized output, the audit trail, exports or imports (imports deliberately take no keys). The form only takes a new key or removes the stored one; the key typed in is cleared from the page state. The audit trail records that a key changed, never its value.
- **Showing a key** requires `itam.license.reveal_key` (asset managers and team leads) **and** a fresh code from the authenticator app (or a recovery code), as re-authentication (CLAUDE.md §6, ASVS L3 for secrets). Each showing is a new audit event, `secret_revealed`, added in Core because it applies to any stored secret; each wrong code is `mfa_failed`. Attempts are limited to five per minute per person.
- **Permissions (owner's decisions, as for assets):** agents see licences and give or release seats; asset managers and team leads manage licences, products, import and export, and may show keys. Employees see the names of their software on My equipment, never keys or costs.
- **Reminders:** `itam:licenses:remind`, daily at 07:20, one email per organization to licence managers about licences expiring within `ITAM_LICENSE_REMINDER_DAYS` (60), once per expiry date. It shares its email with the warranty reminders.
- **Import and export** follow the assets': same page with a choice, matched by product and licence name, all rows or none; the export has the import's columns plus the seats in use and the contract, and no keys. Contract pages list the licences under them.

## Alternatives considered

- **Refusing seats beyond the quantity bought:** keeps numbers tidy, but people would stop recording real installations, which is what an audit needs.
- **Keys shown to everyone who sees the licence, or after the password:** simpler, but the password is not asked again for AD accounts and is often remembered by the browser; the app code proves presence.
- **Software discovery (agents, device management):** gives installed counts automatically; a natural next step through the import, out of scope here.

## Consequences

- Compliance is only as good as the seats recorded; discovery data can later feed the same tables.
- People who leave keep their seats until released; a report of seats held by inactive people is a natural follow-up, like for assets.
