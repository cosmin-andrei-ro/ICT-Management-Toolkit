# ADR 0011 — Form schemas for configurable forms

- **Status:** Accepted
- **Date:** 2026-11-04

## Context

The service catalog needs a form per service, defined by administrators. `CLAUDE.md` §5.2 asks for custom fields stored as `jsonb` with a validation schema defined in Core, so other modules (CMDB CI types, the application documentation sheet) can reuse it.

## Decision

- `Modules\Core\Domain\Forms\FormSchema` is a list of `FormField`s: a key (snake_case, unique in the form), a label, a type, a required flag, help text, and options for select fields. The types are `text`, `textarea`, `number`, `date`, `select` and `checkbox`. A form has at most 30 fields.
- **Definitions are stored as a JSON list** (e.g. `ticketing_services.form_schema`) and validated whenever they are saved; an invalid definition is never stored. In the admin panel the same validation surfaces as a form error.
- **Answers are validated with Laravel rules derived from the schema** (`FormSchema::answer()`). They are stored as a **snapshot**: `{key, label, type, value}` per field, in the owning record (for tickets, `custom_fields.service_form`). Unknown keys are dropped.
- Answers are rendered escaped, never as HTML.

## Rejected alternatives

- **Storing only `{key: value}`:** editing or removing a field would make past answers unreadable or misleading, which is bad for audit.
- **EAV tables** (one row per answer): more joins and no benefit for display-oriented data that is not queried field by field.
- **A form-builder package:** more surface than six field types need, and none integrates with our validation and audit model.

## Consequences

- Reporting on a single answer across many tickets requires a `jsonb` query (`custom_fields->'service_form'`). Fields that need reporting should become real columns.
- New field types (file, user picker) need a new `FieldType` case and rendering in each UI that shows forms.
