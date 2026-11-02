# ADR 0031 — Risk register and scoring method

- **Status:** Accepted
- **Date:** 2026-11-24

## Context

ISO/IEC 27001:2022 §6.1.2 and §8.2 ask for an information security risk assessment with defined criteria, repeated at planned intervals; ISO/IEC 27005:2022 describes it; NIS2 Art. 21(2)(a) and DORA Art. 6 ask for risk analysis within a risk-management framework. The toolkit already knows much of what makes risks — applications and their data, CMDB items, BIA processes, continuity plans, suppliers — and should use it rather than duplicate it.

## Decision (owner's choices)

- **Module `Risk`** (`risk_*` tables), part A of four: register and method; treatment and acceptance; suggestions from the other modules; reporting.
- **Scoring:** likelihood 1–5 × impact 1–5. Per organization (`risk_settings`, defaults until saved): the label and meaning of each level, the bands (low below *medium from*; medium, high and critical from given scores; defaults 5, 10, 17), the score from which accepting a risk needs a risk manager (default 10), the reassessment interval (default 12 months). Inherent risk now; residual risk with treatment (part B).
- **Identification:** event-based risk scenarios (ISO/IEC 27005:2022), each with an owner, a category, the threats and vulnerabilities involved from an organization catalog (starter entries inspired by ISO/IEC 27005 annex A and ENISA's threat landscape; editable, entries in use deactivated rather than deleted), the controls in place, and what it concerns: **applications** (new Core contract `Applications`, implemented by the Applications module), **CMDB items** (`ConfigurationItems`), **business processes** (`BusinessProcesses`) and **suppliers** (Core). References are checked through the owning module and kept by id without foreign keys.
- **Roles:** new default role **Risk manager** (register, method, catalog); the risk owner assesses their risk (score, threats and vulnerabilities, controls, what it concerns) without a permission; security officers, continuity coordinators, application managers and team leads read the register.
- **Register:** numbered R-0001 per organization; heat map of open risks by likelihood and impact, filters by cell, level, category; *My risks* for owners outside the readers.
- Every change is audited on the risk (catalog changes as before/after lists).

## Alternatives considered

- **Asset × threat × vulnerability rows** (classic asset-based method): more systematic, many more rows; scenarios linked to several assets read better for management and still cite threats and vulnerabilities.
- **Impact taken from the BIA:** BIA impact is per process over time; risks concern more than processes. The BIA's objectives are shown where relevant (later suggestions use its gaps).
- **Fixed 4 × 4 scale:** simpler but coarser; rejected.

## Consequences

- Changing the bands re-levels every risk at once (scores are stored, levels computed).
- Parts B–D add treatment and acceptance with approval above the threshold, suggestions from every module (including initial risks when an application's documentation is approved, CLAUDE.md §9), and reports.
