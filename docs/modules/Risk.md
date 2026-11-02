# Risk module

The risk register and the organization's scoring method. Code: `modules/Risk/`. Decisions: [ADR 0031](../adr/0031-risk-register.md).

## Concepts

- **Method** (`risk_settings`, one row per organization; defaults until saved): five likelihood and five impact levels with their meaning, the bands of likelihood × impact (low / medium / high / critical), the score from which accepting a risk needs a risk manager, the reassessment interval.
- **Catalog** (`risk_catalog_entries`): threats and vulnerabilities; a starter set on first use; entries in use are deactivated, never deleted.
- **Risk** (`risk_risks`): reference R-0001 per organization, scenario title and description, category, owner, status (open / closed), inherent likelihood and impact, controls in place, next review. Threats and vulnerabilities in `risk_risk_catalog`; what it concerns in `risk_risk_assets` (application, CMDB item, business process, supplier — by id, checked through Core contracts `Applications`, `ConfigurationItems`, `BusinessProcesses` or Core's suppliers).

## Screens

- `/risks`: the register with the heat map of open risks; filters by cell, level, category, closed.
- `/risks/new`, `/risks/{id}/edit`: identification (risk managers).
- `/risks/{id}`: what it concerns, threats and vulnerabilities, controls, inherent score with the meaning of each level.
- `/risks/mine`: risks the signed-in person owns, for owners without `risk.risk.view`.
- Administration → Risk → Risk method; Threats and vulnerabilities.

## Permissions

| Permission | Default roles | Purpose |
|---|---|---|
| `risk.risk.view` | risk-manager, security-officer, continuity-coordinator, application-manager, team-lead | Read the register |
| `risk.risk.manage` | risk-manager | Register, edit, close, delete risks; assess any risk |
| `risk.settings.manage` | risk-manager | Method and catalog |

A risk's owner assesses it without a permission.

## Plan

1. Register, method, catalog, heat map — **done**.
2. Treatment: options, actions (optionally service desk tickets), residual risk, acceptance with risk manager approval above the threshold, score history, reassessment reminders.
3. Suggestions from the other modules (applications, continuity, operations, suppliers), including initial risks when an application's documentation is approved.
4. Reporting: inherent vs residual heat maps, risks on application pages, Excel register, PDF report, weekly digest.
