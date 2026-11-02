# Application Technical Documentation — template structure

Source: the owner's official template *Application Technical Documentation (Template)*, "Single point of reference for how the application is built, run and supported" (Word/PDF, 22 pages). This file records its structure so the Applications module matches it exactly (CLAUDE.md §9). Values shown with * are dropdowns (*Choose an option*); their lists were not part of the PDF and are administered in the application (*Administration → Application portfolio → Dropdown values*).

The status of each part says where it lives in the toolkit: **built**, or **planned** with the pull request of the plan in [docs/modules/Applications.md](../modules/Applications.md).

> Never store passwords, keys or secrets in this document — reference the vault location only.

## Cover and document control

| Template | Fields | Toolkit |
|---|---|---|
| Cover | Application Name, Application Code, Department Owner, Department Email | Application register — built |
| How to use this template | Instructions; review whenever the application changes, after major incidents, DR tests or audits, at least once a year | Manual; yearly review date — built |
| Change History | Version, Date, Author, Description of Change (one row per published version; production applications start at 1.0 "Initial documentation…") | Versions in force, numbered 1.0 / 1.1 / 2.0 — built |
| Document Control | Document Owner, Last Review Date, Next Review Date, Document Location | Built (review dates follow the approvals) |
| Approvals (first version only) | Role (Business Owner, IT / Application Owner, Security Officer), Name, Approval Date, Comments | Built — owner's decision: v1.0 **and major versions** are approved; minor versions are logged |

## 1. General Information — built

- **1.1 Application Description** — two or three sentences: what it does, who uses it, which business process it supports.
- **1.2 Support & Escalation** — internal teams first, then the vendor.
  - **1.2.1 Internal Support** — a contact per team: Business Owner Team, Service Desk (L1), Application Support (L2/L3), Technical Business Analysts Team, Development Team, Architecture Team, Robotic Process Automation (RPA) Team, Infrastructure Team, Network Team, Database Team, Identity & Access Management (IAM) Team, Security Operations (SOC) Team, Security Governance, Risk & Compliance (GRC) Team, Audit Team, Legal Team.
  - **1.2.2 Vendor Support** — Vendor Company, Contract Number, Support Portal, Support Email (vendor and contract from Core's suppliers and contracts).
- **1.3 Service Classification** — Application Status*, Business Criticality*, Exposure (☐ Internal ☐ Internet-facing ☐ External 3rd Party), Application Cartography Profile*.
- **1.4 Environments** — table: Environment*, URL, Purpose, Access Notes. Every environment, including Production and Test.
- **1.5 Related Documentation & Repositories** — table: Type*, Name, Link.
- **1.6 Service Levels & Maintenance** — Availability Target (SLA), Support Hours*, Incident Response / Resolution Times, Planned Maintenance Window, Change Freeze Periods, Maintenance Notification.

## 2. Data Flows & Functionalities — built

- **2.1 Data Categories** — Data Classification* (the most sensitive category); table Data Category / Data Elements Processed with tick boxes:
  - Personal Data (GDPR): Name, Contact details, National ID / passport, Date of birth, Address, IP address / online identifiers, Photo / video
  - Special Category Personal Data (GDPR Art. 9): Health, Biometric, Genetic, Racial / ethnic origin, Religious beliefs, Political opinions, Trade union membership, Sexual orientation
  - Employee / HR Data: Contracts, Payroll / salary, Performance evaluations, Attendance / leave, Recruitment data, Background checks
  - Payment Card Data (PCI DSS): PAN, Cardholder name, Expiry date, CVV / CVC, PIN, Magnetic stripe / chip data
  - Financial & Banking Data: Account numbers / IBAN, Balances, Credit / loan data, Statements, Investment / securities data
  - Business Transactions: Payments / transfers, Orders, Clearing & settlement, Binding contracts, Invoices issued
  - Accounting & Tax Data: General ledger, Invoices, Tax returns, Audit records, Budgets / forecasts
  - Legal & Compliance Data: Contracts, Litigation files, Regulatory filings, Legal opinions, AML / KYC records
  - Medical / Health Records (HIPAA): Patient records, Diagnoses, Prescriptions, Lab results, Insurance claims
  - Customer & Commercial Data: CRM records, Pricing, Offers / quotes, Sales pipeline, Marketing lists
  - Product & Intellectual Property: Designs / drawings, Formulas / recipes, Source code, R&D data, Patents / trade secrets
  - Manufacturing / Operational (OT) Data: Production parameters, Sensor / SCADA data, Quality records, Maintenance logs
  - Logistics & Supply Chain Data: Shipments, Inventory, Supplier data, Tracking / GPS
  - Telecom & Location Data: Call detail records, Traffic / usage data, Geolocation, Subscriber data
  - Authentication & Security Data: Passwords / hashes, Keys / certificates, Tokens / sessions, Security logs, MFA data
  - IT & Infrastructure Data: Hostnames / IP addresses, System configuration, Application / system logs, Asset inventory (CMDB), Network topology, Vulnerability / scan results
  - Communications & Support Data: E-mails, Chat / messaging, Call recordings, Support tickets, Meeting recordings
  - Web & Usage Analytics: Cookies, Device identifiers, Clickstream / browsing history, Usage statistics, Behavioural profiles
  - Education / Student Data: Student records, Grades / transcripts, Enrolment data, Attendance, Parent / guardian details
  - Government / Public Sector Data: Citizen records, Tax / social security IDs, Permits / licences, Case files, Classified / restricted information
  - Public Data: Published content, Marketing material, Public catalogues / prices
  - Other: free description of the data type and its elements
- **2.2 Data Flow Diagram** — Yourdon / DeMarco notation, Level 0 and Level 1 (Level 2 for critical applications); image and .drawio file. Toolkit: a context diagram (level 0) drawn from 2.4, plus the team's own image and .drawio file (owner's decision).
- **2.3 Application Functionalities** — table: Module, Menu / Screen, Functionality, Description, Input, Output (names reused in 3.4).
- **2.4 System Integrations** — table: Integration, System, Direction*, Type*, Data / Format, Frequency*, Owner. One row per automated exchange; its network side goes in 4.3.

## 3. User Management & Roles — built

- **3.1 Authentication & Identity** — AD Integrated*, Authentication Protocol*, AD Domain / LDAP Server, IAM Integrated*, IAM Resource / Entitlement, Multifactor Authentication*, MFA Method, Single Sign-On (SSO)*.
- **3.2 Access Request Procedure** — 3.2.1 Application Users; 3.2.2 Administrative & Technical Users (text).
- **3.3 Application Roles** — table: Role Name, Description, AD Group / IAM Entitlement.
- **3.4 Role Permissions Matrix** — Module, Menu / Screen, Functionality × the roles of 3.3 (tick boxes).

## 4. Network — built

- **4.1 Network Description** — DNS Names / Aliases, Load Balancer / VIP, Load Balancing Method*, TLS Termination*.
- **4.2 Network Architecture** — security zones (Internet, DMZ, application, data, management), firewalls, servers and permitted connections; image and .drawio file. Toolkit: drawn from 4.3 (one box per source or destination, one arrow per rule), plus the team's own image and .drawio file.
- **4.3 Firewall Rules & Ports** — table: Source, Destination, Port, Direction*, Protocol*, Encryption Method*, Purpose, Ticket ID.

## 5. Infrastructure — built

- **5.1 Hosting** — Hosting Model*, Data Center / Cloud Provider, Platform*.
- **5.2 Components** — table: Component*, Server Name, IP Address, Operating System*, Type, Description, Env.*, Ticket ID; plus links to the CMDB items, which then list the application.

## 6. Software & Platforms — built

- **6.1 Application Architecture** — Application Type*, User Access Method*, Main Tiers / Layers, Architecture Notes.
- **6.2 Backends** — Framework, Framework Version, Server(s), Role, App Server*, App Server Version, Language & Version*, Config / Logs Path.
- **6.3 Frontends** — Name, Client Type*, Framework / Technology, Framework Version, Language, Build Tool, Distribution / Installation*, Client Prerequisites.
- **6.4 Databases** — DBMS*, Version, Host:Port / Service Name, Schemas / Databases, High Availability*. **6.4.1 Database Diagram**: entity-relationship diagram, image and .drawio file (upload).
- **6.5 Cache & Middleware** — Product, Version, Server / Endpoint, Purpose.
- **6.6 Third-party Libraries & Licenses** — Name, Version, License*, Vendor, Support Until (date).

## 7. Technical Users / Service Accounts — built

Username, IAM Category*, User Type, Usage / Purpose, Systems Used On, Owner, Password Rotation*, Credential Location (vault only), Ticket ID.

## 8. Security & Compliance — planned (PR 4)

- **8.1 Certificates** — Name / CN, Type*, Used For, Issuer, Expiry Tracking, Alert Lead Time, Renewal Owner.
- **8.2 Secrets** — Secret Name, Type*, Used By, Vault / Location, Rotation, Owner (never the secret itself).
- **8.3 Compliance Requirements** — Regulation / Standard, Scope / Applicability, Evidence / Report, Last Audit, Owner.

## 9. Logging, Monitoring & Audit — planned (PR 4)

- **9.1 Audit Logs** — application, OS and database audit logs: Location, Forwarded To, Collection Method.
- **9.2 Technical Logs** — Log Name, Type*, Description / When to Check, Server, Path, Rotation / Retention.
- **9.3 Monitoring & Alerting** — Monitoring Tool(s), Dashboards, Health-check URL(s); alerts: Alert Name, Condition / Threshold, Severity*, Notified Team / Recipients, Channel*, Action / Runbook.

## 10. Business Continuity & Disaster Recovery — planned (PR 4)

- **10.1 Business Impact** — Business Processes Supported, Departments / Teams Impacted, Impact if Unavailable, Critical Periods, Key Roles Required to Operate, Minimum Staffing (Degraded Mode), Other Resources Required, MTPD, RPO, RTO, DR Site, Recovery Priority* (from the BIA where the processes are assessed).
- **10.2 Backups** — What is Backed Up, Method / Tool*, Frequency, Retention, Owner, Ticket ID.
- **10.3 Dependencies** — System / Service, Direction*, Criticality*, Recovery Order, Notes (from CMDB relationships).
- **10.4 Workarounds / Degraded Mode** and **10.5 Restore & Failover Procedure** (text; linked to the BCM recovery plan).

## 11. Deployment & Release — planned (PR 4)

Source Code Repository, Change Management; **11.1 Deployment Procedure** and **11.2 Rollback Procedure** (text).

## 12. Scheduled Jobs / Batch Processing — planned (PR 4)

Job Name, Scheduler*, Schedule, Description, Dependencies, If It Fails.

## 13. Operational Procedures (Runbook) — planned (PR 4)

- **13.1 Start / Stop Order** — Step, Component, Server, Command, Verification.
- **13.2 Health Checks After Restart** (text).
- **13.3 Common Administrative Tasks** — Task, Frequency*, Procedure / Command.

## 14. Troubleshooting & Known Issues — planned (PR 4)

Symptom, Probable Cause, Resolution, Ref. Ticket.

## 15. Decommissioning — planned (PR 4)

- **15.1 Decommissioning Overview** — Reason for Decommissioning*, Replaced By (Application), Approval & Change Ticket, Data Retention Period & Legal Basis, Archived Data Location & Access.
- **15.2 Decommissioning Checklist** — Step, Activity, Owner, Ticket ID, Status*. Default steps: inform users, business owner and support teams; archive or export data; remove user access (3.3); disable technical users (7); revoke certificates and remove secrets (8); remove firewall rules, DNS entries and load-balancer VIPs (4.1, 4.3); disable scheduled jobs (12); remove monitoring, alerting and log forwarding (9); stop backup jobs once archives are verified (10); shut down and release servers (5.2); cancel licences and vendor contracts (1.2.2, 6.6); update the CMDB / application cartography and archive the document.

## 16. Glossary & Abbreviations — planned (PR 4)

Term, Meaning: the template's standard glossary (AD, AML, API, CAB, CHG / INC, CI/CD, CMDB, CN, DBA, DBMS, DC, DFD, DMZ, DPIA, DR, ERD, ESB, FK, GDPR, GRC, HIPAA, IAM, KYC, L1 / L2 / L3, LB, LDAP, MFA, MTPD, NIS2, OIDC, OS, OT, PAM, PCI DSS, PK, PKI, REST, RPA, RPO, RTO, SAML, SLA, SOC, SSH, SSO, TLS, UAT, VIP, VLAN, VM, VPN) plus application-specific terms.
