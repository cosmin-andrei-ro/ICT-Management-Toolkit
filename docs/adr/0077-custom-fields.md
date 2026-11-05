# ADR 0077 — Custom fields

- **Status:** Accepted
- **Date:** 2026-11-05

## Context

`CLAUDE.md` §5.2 asks for custom fields per kind of record, in `jsonb` with a schema defined in Core. They existed only on CMDB item types and service forms. On 2026-11-04 the owner chose the main records (tickets, configuration items, assets, applications, risks, projects, suppliers), defined by administrators per kind of record, with an audit trail.

## Decision

- **One schema per organization and kind (`core_custom_field_schemas`),** reusing `FormSchema` and its admin editor, so types and validation are the ones already tested.
- **Values in `core_custom_field_values`, polymorphic,** not a column in each module's table. It is a cross-cutting feature (§5.2), and modules do not need a migration each.
- **Modules register their kinds** in `CustomFieldTypes`, so a disabled module's kinds do not appear.
- **A box on the record's page.** Whoever may view the record reads the values; whoever may change it edits them, with the same right as for the record (`update`, or `work` for tickets). Both are checked on the record at each action.
- **Values are stored by field key and audited on the record itself,** so they appear in its history.
- **Removing a field hides it.** Its values are kept, and come back if a field with the same key is added.
- **A required field applies when someone edits the box,** not when creating the record: forms of seven modules stay as they are, and existing records do not become invalid.

## Alternatives considered

- **A column in each module's table:** seven migrations and seven copies of the same code.
- **Fields inside each module's create and edit form:** more natural to fill in, and required fields could be enforced on creation, but seven forms to change. It can follow for the kinds that need it.

## Consequences

- People may type personal data into a custom field, and it is not in the personal data export or anonymization. Administrators should not ask for it; the admin help does not forbid it.
- Custom fields are not in searches, lists, exports or the API yet.
- CMDB items and assets also have attributes per type or category. Those remain the place for what depends on the type.
- Tickets show the box to agents only, on the desk page. Requesters do not see it in the portal.
