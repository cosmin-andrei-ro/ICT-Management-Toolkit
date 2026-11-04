# Features

What each module does today.

## Core
- Several organizations (legal entities) in one installation, with their data kept apart.
- Sign-in with local accounts, **Active Directory** (LDAPS) or **single sign-on** through each organization's OpenID Connect or SAML 2.0 provider, with roles from its groups. For SAML, optional single logout in both directions and encrypted assertions. AD groups are mapped to roles, and accounts are re-checked every 15 minutes.
- **Two-factor authentication** with an authenticator app, required for IT staff, plus recovery codes; **security keys and passkeys** (WebAuthn), also to sign in without a password.
- **People management**: invitations to local accounts by email, roles, deactivation. Password reset by email and password change for local accounts.
- Role-based permissions: requester, agent, team lead, CAB member, asset manager, continuity coordinator, application manager, security officer, risk manager, compliance manager, auditor, project manager, administrator.
- **Suppliers** with their support contacts, **contracts** with documents, deadlines and email reminders before each notice deadline, and nested **locations** (sites, buildings, rooms).
- Teams, business calendars (working hours, Romanian public holidays), API tokens that IT staff issue themselves, bound to one organization, with administrators able to revoke any of them, and signed outbound webhooks for events from tickets, changes, the CMDB, assets, risks, findings and continuity plans.
- Append-only **audit trail**: who changed what, when, with old and new values; sign-ins, role changes, exports.
- Administration panel at `/admin`, a REST API at `/api/v1` for tickets, the CMDB, assets and licences, risks, controls and findings, business processes, continuity plans and applications, a monitoring endpoint (own format and [Prometheus Alertmanager](https://prometheus.io/docs/alerting/latest/alertmanager/)) that opens and resolves incidents linked to CMDB items, plus read-only access to the directory, suppliers and contracts, the data centre, documentation, projects and indicators (OpenAPI in [docs/api/openapi.json](api/openapi.json)), and the **user manual** at `/manual`.
- **Search** in the page header, across every module, showing only what the person may open.
- **Attachments** checked by content, with optional antivirus scanning ([ClamAV](https://www.clamav.net)).
- **Tags** on tickets, configuration items, assets, applications, risks, projects and suppliers, with a page listing what carries each one.

## Ticketing
- **Incidents and service requests**: self-service portal, service catalog with request forms, agent desk with queues, assignment, internal notes, attachments and full-text search.
- **Approval of catalog requests** by people named per service, before IT starts on them.
- **SLAs**: response and resolution clocks in working time, with warnings and breach notifications.
- **Team targets (OLA)** per team, with compliance per team in the reports, and an **escalation contact** per queue told about targets missed and still open.
- **Problems** and a **known error database**.
- **Changes**: standard, normal and emergency, with CAB and emergency CAB (ECAB) approval, a change calendar, and post-implementation reviews.
- **Email-to-ticket**: tickets and replies by email, from IMAP mailboxes (e.g. Exchange).
- **Reports**: resolution times, SLA compliance, reopen rate, backlog and volume, exported to XLSX or CSV.

## CMDB
- **Configuration items** of extensible types (servers, applications, databases, services…) with their own attributes, and typed **relationships** between them.
- **Impact analysis**: what a failure affects and what an item needs, plus an interactive dependency map.
- Items linked to tickets, so incidents show the services they take down and the CAB sees what a change touches.
- **Import** from CSV or XLSX, with preview.

## IT asset management
- **Hardware assets** of extensible categories, with automatic asset tags, purchase and warranty details, and a life cycle from order to disposal (with data wiping recorded).
- Who has each asset, from when to when; **My equipment** for every employee.
- Assets linked to CMDB items and to the support contracts that cover them; warranty reminders by email.
- **Handover protocols** to print or save as PDF, with the employee's confirmation of receipt in the portal; **QR labels**; **import** from CSV or XLSX and **export** of the register.
- **Software licences** per user, per device or site: seats in use against seats bought per product, over-use flagged, keys encrypted and shown only after an authenticator code (audited), expiry reminders.

## Business impact analysis
- **Business processes** with owners in the business, assessed in an **impact matrix over time** (financial, legal, reputation, operations, customers × 4 hours to 1 month); MTPD derived from the scores, RTO checked against it, RPO, workarounds; recovery tiers.
- Versioned assessments: owners submit, a continuity coordinator approves or returns them (never their own); yearly review.
- **Dependencies** on IT systems (CMDB), suppliers, locations, other processes, key roles and people; **recovery gaps**: the strictest RTO/RPO each system must meet, through CMDB relationships, against the recovery capability IT declared, with suggested criticality.
- Register export to XLSX, BIA sheet per process to print or as PDF, review reminders to owners and a weekly digest for coordinators.

## Continuity and recovery (BCM/DRP)
- **Business continuity and IT disaster recovery plans**, versioned and approved: scope (BIA processes, CMDB systems), scenarios, activation, crisis team, communication, and a **runbook** whose recovery time is compared with the RTO.
- Plan in force to print or as PDF, for offline use during a crisis.
- **Exercises and recovery tests**: the runbook in force step by step with results and times, measured recovery against the RTO, systems confirmed as recovered in the CMDB, findings raised as problems in Ticketing, report to print or as PDF.
- **Coverage report**: assessed processes without a plan in force or not brought back within their RTO, plans not approved or overdue for review or exercise; XLSX export of the continuity register; reminders to owners and a weekly digest for coordinators.
- **Activation log** for real disruptions: the crisis team is emailed a link to the plan, time to recover is compared with the RTO, lessons are kept.

## Application portfolio
- Register of applications with business owner, IT / application owner and deputy.
- **Technical documentation sheet** following the company's template chapter by chapter, with editable dropdowns: general information, support and classification; data categories (GDPR, PCI DSS…), functionalities and integrations with a **data flow diagram drawn from them** (plus the team's own diagram and .drawio file); authentication, access procedures, roles and the **role permissions matrix**; network with firewall rules and a **network diagram drawn from them**, hosting and components **linked to the CMDB**, software, databases, middleware, libraries and licences, technical accounts (vault location only); certificates with **expiry reminders**, secrets (vault location only), compliance, logs and alerts; continuity **linked to the BIA processes**, CMDB dependencies and recovery plans; deployment, scheduled jobs, runbook, known issues, decommissioning checklist and glossary.
- Versioned: the first version and major changes are approved by the business owner, the IT owner and a security officer; minor changes are published and logged in the change history.
- **Next steps** after approval: proposes assessing a business process and recording licences, which a person confirms.
- **Full document** to print, or **PDF** through the optional [Gotenberg](https://gotenberg.dev) service; the application gets its own CMDB item, running on its components, and its page in Documentation (summary regenerated at every version, team notes kept), when its documentation comes into force; yearly review reminders, a weekly digest for application managers and an XLSX export of the portfolio.

## Risk assessment
- **Risk register** of risk scenarios linked to the applications, CMDB items, business processes and suppliers they concern, with the threats and vulnerabilities involved from an editable catalog and the controls in place.
- Configurable **5 × 5 method** (level meanings, risk bands, approval threshold, reassessment interval) and a **heat map**; owners assess their risks.
- **Suggested risks** from the other modules - application data and exposure, certificates, unsupported components, recovery gaps, processes without a plan, recurring incidents, expiring licences, contracts and warranties, supplier dependencies, open major nonconformities, requirements without controls - registered as prefilled risks or dismissed with a reason, never created silently.
- **Treatment**: option, plan, **residual risk**, actions with owners and due dates (optionally tracked in Ticketing); **acceptance** of the residual risk, approved by a risk manager other than the owner from the threshold; score history and reassessment reminders.
- **Reports**: heat maps before and after treatment, the risk report to print or as PDF, the register in XLSX, open risks on each application's page, a weekly digest for risk managers.

## Audit & compliance
- **Frameworks**: ISO/IEC 27001:2022 Annex A (identifiers with the toolkit's own titles), NIS2, GDPR and DORA, added with all their requirements, and your own frameworks.
- **Applicability** of each requirement with its justification: the Statement of Applicability for ISO/IEC 27001.
- **Controls** with owners and implementation status, each mapped to requirements of several frameworks at once; each framework's coverage follows from them.
- **Evidence** for each control: records of the toolkit (approved changes, exercises and recovery tests, risks, applications, contracts…) and uploaded documents with an expiry date; **periodic control tests** with results, the document they produced and reminders to owners.
- **Audits** (internal and external) and their **findings** - nonconformities, observations, opportunities - also raised from failed control tests; root cause, **corrective actions** (optionally tracked in Ticketing) and closure verified by a compliance manager other than the owner.
- **Reports**: the **Statement of Applicability** and the **compliance report** to print or as PDF, everything in XLSX, a weekly digest for compliance managers; open major nonconformities and uncovered requirements suggested as risks.

## Documentation
- **Spaces** read by IT staff, by everyone or by chosen teams, written by their owners and editing teams.
- **Pages** in a tree, written in Markdown with a toolbar and live preview; every save kept as a version, with a line diff and restore; saving over someone else's newer version is refused.
- **Controlled spaces** for procedures and policies: drafts published once a space owner other than the author approves them; review dates with a weekly digest for owners; pages to print or as PDF with their approval.
- **Help for employees**: articles of spaces open to everyone suggested while a ticket is written in the portal, and shown on tickets they are linked to; agents see linked and suggested articles on every ticket.
- **Full-text search** across the spaces you read; pages linked to applications, CMDB items, plans, risks, contracts or tickets, and files attached.

## Reporting
- **Dashboard** on the home page with the figures of every module you have access to, coloured by status and linked to the filtered lists behind them; hide figures and reorder groups to suit you.
- **Trends**: every figure is recorded once a day; each one shows its last 30 days on the dashboard and has its own page with a chart over 30, 90 or 365 days (drawn on the server, so it also prints).
- **Management overview** of every module's figures, and a **monthly management report** to print or as PDF, emailed on the first of the month to the people an administrator chose.

## Data centre (DCIM)
- **Racks** in the organization's rooms, drawn from the front and from the rear; assets of the asset register mounted on their units (full or half depth, side 0U equipment), never two on the same unit; the asset's page says where it is mounted.
- **Cabling**: ports on each device (added in series, patch panels with their rear ports), labelled cables between them, one per port; every connection traced end to end through the patch panels.
- **Power**: feeds, UPSs and PDUs in a chain with their capacity; load from each device's draw, in normal operation and if the other side fails; equipment without two independent supplies; what a UPS or PDU failure takes down, up to the services in the CMDB; single-fed equipment suggested as risks.
- **IP addresses**: VLANs, IPv4 and IPv6 subnets nested by containment with gateway and purpose, addresses given to equipment or CMDB items; utilisation, the next free address, and no duplicates (checked by [PostgreSQL](https://www.postgresql.org)'s own network types).
- **Reports**: data centre indicators on the dashboards and in the monthly report; the whole data centre in XLSX; a rack sheet to print or as PDF with both sides, the equipment, its power and its cables.

## Projects
- **Projects** with a code, sponsor, manager and team, in four phases (initiation, planning, execution, closure) with their dates.
- **Tasks** per phase on a board (to do, in progress, blocked, done) or as a list; overdue tasks marked; **My tasks** across every open project.
- **Stage gates**: the project manager asks to move to the next phase or to close; the sponsor approves or sends it back with a reason; emails both ways.
- **Status reports** green / amber / red (overall, schedule, budget, scope) with what was done, next steps and issues; weekly reminders when one is due; a **portfolio** of open projects for management.
- **Deliverables** per phase linked to what they became in other modules (the application, a CMDB item, a change, a plan, a page…); for a **new application**, a checklist read live from the modules - documentation approved, CMDB item, risks assessed, BIA processes with RTO - that, with the required deliverables, must be complete before the sponsor can close the project.
- **Budget**: approved amount against costs by category (external labour, licences, hardware, services), with suppliers and invoices; over-budget projects flagged; project indicators on the dashboards and in the monthly report; the portfolio in XLSX; each project's status report to print or as PDF.
