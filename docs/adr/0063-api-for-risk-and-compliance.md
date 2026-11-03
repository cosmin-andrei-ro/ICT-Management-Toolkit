# ADR 0063 — API for the risk register and compliance

- **Status:** Accepted
- **Date:** 2026-12-26

## Context

GRC tools, reporting pipelines and audit-preparation scripts need to read the risk register and the compliance picture. Some also need to update them: a vulnerability scanner registering risks, a monitoring job recording a control test. The owner decided (2026-11-03) that **approvals never go through the API**, so risk acceptances and finding verification stay with people on screen. The conventions are those of ADR 0061 and 0062.

## Decision

- **Abilities:** `risks:read`, `risks:write`, `compliance:read`, `compliance:write`. Policies are exactly the screens':
  - a risk's owner assesses and treats it, risk managers change the register;
  - a control's owner records implementation, evidence and tests, compliance managers create, change and map controls;
  - a finding's owner (or a compliance manager) works on its actions.
- **Risk register:**
  - risks are registered and changed (POST/PATCH);
  - assessment and treatment each have a PUT endpoint, matching the screen's two steps and their actions (`AssessRisk`, `SaveTreatment`), so the score history and the lapsing of acceptances follow;
  - what the risk concerns, and its treatment actions, are added and moved on;
  - scores come with levels on the organization's own method;
  - the catalog is readable.
  - The owner must be an active member of the organization. The screen checks it in the form, so the API checks it too, since the action does not.
- **Compliance:**
  - frameworks, their requirements (applicability, state, mapped controls) and audits are read-only;
  - controls are created, changed and mapped;
  - implementation, evidence and tests have their own endpoints. Evidence is **links** to toolkit records only, resolved with the caller's permissions through `LinkedRecords`; file uploads stay on screen.
  - Findings are raised, also from an audit, and changed, root cause included; corrective actions are added and moved on.
- **Not exposed:** requesting or deciding a risk acceptance, the risk suggestions, submitting or verifying a finding, the audit lifecycle, and statement-of-applicability decisions.

## Alternatives

- **One PATCH carrying the assessment and treatment fields:** rejected. The screens and actions keep them apart, because the owner assesses but only some changes void an acceptance. Separate endpoints keep that meaning visible.
- **Uploading evidence files through the API:** postponed. Uploads need the same scanning, size and type checks as the screen. A multipart endpoint can come later if integrations ask for it.

## Consequences

- An integration can run the register and the control tests end to end. Every acceptance and every closure of a nonconformity still carries a person's decision.
- `docs/api/openapi.json` describes the new endpoints.
