# ADR 0034 — Compliance frameworks, controls and the Statement of Applicability

- **Status:** Accepted
- **Date:** 2026-11-27

## Context

Organizations under NIS2, DORA or GDPR, or certified against ISO/IEC 27001, must show which requirements apply to them, which controls meet them and how far those controls are implemented. One control (for example *multifactor authentication on remote access*) usually meets requirements of several frameworks at once: maintaining it once per framework duplicates work and drifts. ISO/IEC 27001 also asks for a Statement of Applicability (6.1.3 d): every Annex A control, whether it is included and why.

## Decision (owner's choices)

- **Module `Audit`** (`audit_*` tables), part A of four: frameworks, controls and applicability; evidence and control tests; audits and findings; reporting.
- **Frameworks** per organization (`audit_frameworks`), each with its **requirements** (`audit_requirements`: reference, title, optional wording or guidance, section, position). The toolkit ships four, added on demand with all their requirements from `modules/Audit/resources/frameworks/*.php`:
  - **ISO/IEC 27001:2022 Annex A**: the 93 control identifiers with the toolkit's **own short titles**. The standard's text is copyrighted by ISO and is not distributed; organizations may enter it from their licensed copy in each requirement's wording.
  - **NIS2** Art. 20, 21 (each measure of 21(2)) and 23;
  - **GDPR** Art. 5, 25, 28, 30, 32, 33–34, 35, 37;
  - **DORA** Art. 5–14, 17–19, 24–28.
  Descriptions of the EU acts are the toolkit's summaries, not the legal text. **CIS Controls** are not shipped: their licence (CC BY-NC-ND) does not allow it. Organizations add **their own** frameworks (internal policies, customer requirements) and requirements.
- **Applicability** on each requirement: undecided, applicable, not applicable (justification required), with the justification and who decided. For frameworks with `has_soa` (ISO/IEC 27001) this is the Statement of Applicability.
- **Controls** (`audit_controls`): numbered C-001 per organization, name unique, description, owner, status (planned, partly implemented, implemented, retired), implementation notes. Mapped to requirements of any framework (`audit_control_requirement`), many-to-many, with composite foreign keys keeping both sides in the same organization.
- **State of a requirement** is never stored: it follows from its applicability and the status of its controls (not covered, planned, partly met, met; retired controls ignored). **Coverage** of a framework is the share of applicable requirements that are met.
- **Roles:** new default roles **Compliance manager** (frameworks, applicability, controls, mapping) and **Auditor** (reads everything, changes nothing); security officers and risk managers read. A control's **owner** keeps its status and implementation notes without a permission, under *My controls* when they do not read compliance.
- Every change is audited: frameworks, requirements (applicability) and controls on their own trail; mappings on the control.

## Alternatives considered

- **Controls inside each framework** (one row per framework requirement): simpler, but the same control is documented four times and its status diverges.
- **Storing the requirement state:** faster reports, but it must be recomputed on every control change and can drift; frameworks have at most a few hundred requirements.
- **Shipping the ISO/IEC 27001 text:** not allowed by the copyright; titles of our own keep the catalog useful.
- **A global framework catalog shared by organizations:** organizations edit wording and add requirements; copies per organization keep them independent.

## Consequences

- Updating a shipped framework (a new version of a regulation) means adding it again under a new key or adding the new requirements; existing decisions and mappings stay.
- Parts B–D add evidence (links to toolkit records, uploads, periodic tests with reminders), audits with findings and corrective plans, and reports: the Statement of Applicability in Excel and PDF, a digest, and compliance gaps as risk signals.
