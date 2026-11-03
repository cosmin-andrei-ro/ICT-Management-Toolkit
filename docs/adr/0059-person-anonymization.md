# ADR 0059 — Anonymization of a person

- **Status:** Accepted
- **Date:** 2026-12-21

## Context

CLAUDE.md §6 asks for anonymization on request (GDPR Art. 17, right to erasure). People are linked to records in every module, and those records are the organization's own: tickets, risks, audits, changes. They often carry their own retention obligations (Art. 17(3)(b) and (e)). The audit log is append-only and enforced by a database trigger (ADR 0005), yet it holds names, e-mail addresses (failed sign-ins, the creation of the account) and IP addresses.

The owner chose (2026-11-03) to redact the audit log through a single dedicated database function, itself recorded, rather than leave it untouched.

## Decision

- **Anonymize, do not delete, the account.**
  - The user row stays, so every foreign key and every history keeps pointing to it.
  - It loses its personal data: the name becomes "Former user #<last 8 of the id>", the e-mail becomes `former-<id>@anonymized.invalid`, and the password, directory identifiers and two-factor data are cleared.
  - It is deactivated, `users.anonymized_at` is set, and it leaves the organization, its roles and its teams.
  - Sessions, API tokens, security keys, SSO identities and password reset tokens are deleted.
  - The `users_credentials_match_source` check accepts an anonymized account without credentials.
- **Module erasers** (`PersonalData::registerEraser`, given the person and their former address) remove what is only personal:
  - Ticketing: sender address and subject of received e-mails;
  - ITAM: the IP address of handover acknowledgements;
  - Reporting: dashboard preferences and the report subscription.

  Records the person took part in are kept, including the text they wrote. They are the organization's records and are covered by its retention (next ADR).
- **Audit log redaction**, `core_redact_person_in_audit_log(person, name, e-mail, new name, new e-mail)`:
  - It replaces the person's name and e-mail wherever they are a **whole JSON string value** in `old_values` and `new_values`. "Ana Pop" is replaced; "Ana Popescu" and free text containing the name are not.
  - It clears `ip_address` and `user_agent` of the entries the person made.
  - The immutability trigger lets an UPDATE through only while the function has set `core.audit_redaction` for the transaction, and only when every other column (id, organization, actor, event, subject, request id, time) is unchanged. DELETE and TRUNCATE stay forbidden.
  - The anonymization is recorded as `person_anonymized`, by the administrator, with the number of entries redacted and without the old name.
- **Safeguards:**
  - permission `core.personal_data.manage` (administrators);
  - the person's e-mail typed out, and a fresh authenticator code (re-authentication, rate limited, failures audited);
  - refused for oneself, for the last administrator, for an account already anonymized, and for a person who also belongs to another organization. The account is shared, and each organization answers for its own data, so they must remove the person first.
  - It runs in one transaction.

## Alternatives

- **Deleting the account:** rejected. It would break every foreign key or cascade into the organization's records, and erase evidence the organization must keep.
- **Leaving the audit log untouched** (legal-obligation basis): rejected by the owner. The log's purpose — who did what, when — survives the redaction.
- **Matching the name anywhere in text:** rejected. Too many false positives, such as a colleague with a longer name or a word in a description, and it would alter entries about other people.

## Consequences

- The trigger's exception relies on a transaction-local setting. Anyone with direct database rights could use it, but they could also drop the trigger: the protection was always against the application, not against the DBA.
- Free text written by or about the person stays: ticket descriptions, comments, documentation pages. Removing it is a manual, case-by-case decision. Retention (next ADR) handles closed tickets as a whole.
- New places that store personal-only data (contact details, IP addresses) must register an eraser.
