# Risk module

The risk register and the organization's scoring method. Code: `modules/Risk/`. Decisions: [ADR 0031](../adr/0031-risk-register.md), [ADR 0032](../adr/0032-risk-treatment-and-acceptance.md), [ADR 0033](../adr/0033-risk-suggestions-from-modules.md).

## Concepts

- **Method** (`risk_settings`, one row per organization; defaults until saved): five likelihood and five impact levels with their meaning, the bands of likelihood × impact (low / medium / high / critical), the score from which accepting a risk needs a risk manager, the reassessment interval.
- **Catalog** (`risk_catalog_entries`): threats and vulnerabilities; a starter set on first use; entries in use are deactivated, never deleted.
- **Risk** (`risk_risks`): reference R-0001 per organization, scenario title and description, category, owner, status (open / closed), inherent likelihood and impact, controls in place, next review. Threats and vulnerabilities in `risk_risk_catalog`; what it concerns in `risk_risk_assets` (application, CMDB item, business process, supplier — by id, checked through Core contracts `Applications`, `ConfigurationItems`, `BusinessProcesses` or Core's suppliers).

- **Treatment** (on the risk): option (mitigate, transfer, avoid, accept), plan, residual likelihood and impact; `assessed_at`.
- **Actions** (`risk_actions`): title, owner, due date, status; optionally a problem in the service desk (ticket id and number).
- **Acceptances** (`risk_acceptances`): residual score accepted, justification, pending / approved / rejected / withdrawn / lapsed, requester and decider, valid until; `risk_risks.accepted_until` while valid.
- **Score history** (`risk_score_history`): inherent and residual scores at each change.

- **Suggestions** (`risk_suggestions`): risks the other modules signal through Core's `RiskSignals` registry — one per situation key; open, registered (with the risk), dismissed (with a reason) or no longer applies.

## Screens

- `/risks`: the register with the heat map of open risks; filters by cell, level, category, closed.
- `/risks/new`, `/risks/{id}/edit`: identification (risk managers).
- `/risks/{id}`: what it concerns, threats and vulnerabilities, controls, inherent score with the meaning of each level; treatment and residual risk; actions; acceptance (ask, approve, reject); score history.
- `/risks/suggestions`: suggested risks by status and source; register one as a prefilled risk or dismiss it; **Check now** (risk managers).
- `/risks/report` (and `/risks/report/pdf` through Gotenberg, throttled and audited): method, heat maps before and after treatment, risks by level, every open risk with its treatment and actions.
- Register → **Export to Excel** (`risk.register`): risks with inherent and residual scores, treatment, acceptance; actions; acceptances.
- Application pages list their open risks through Core's `Risks` contract (implemented here), for viewers who may read the register.
- `/risks/mine`: risks the signed-in person owns, for owners without `risk.risk.view`.
- Administration → Risk → Risk method; Threats and vulnerabilities.

## Scheduled tasks

- `risk:suggestions:refresh` (daily 06:30): every organization's suggestions brought in line with the modules' signals; also run on Core's `RiskSignalsChanged`.
- `risk:digest` (Mondays 08:10): risk managers get suggestions to review, acceptances waiting or expiring within 30 days, overdue actions and reassessments, if any.
- `risk:reviews:remind` (daily 08:00): the owner of each open risk is emailed `RISK_REMINDER_DAYS` (30) before its reassessment, once per date.

## Permissions

| Permission | Default roles | Purpose |
|---|---|---|
| `risk.risk.view` | risk-manager, security-officer, continuity-coordinator, application-manager, team-lead | Read the register |
| `risk.risk.manage` | risk-manager | Register, edit, close, delete risks; assess any risk |
| `risk.settings.manage` | risk-manager | Method and catalog |
| `risk.acceptance.approve` | risk-manager | Approve or reject acceptances from the threshold (never as owner or requester) |

A risk's owner assesses it without a permission.

## Plan

1. Register, method, catalog, heat map — **done**.
2. Treatment: options, actions (optionally service desk tickets), residual risk, acceptance with risk manager approval above the threshold, score history, reassessment reminders — **done**.
3. Suggestions from the other modules (applications, continuity, operations, suppliers, and later compliance), including initial risks when an application's documentation is approved — **done**.
4. Reporting: inherent vs residual heat maps, risks on application pages, Excel register, PDF report, weekly digest — **done**.
