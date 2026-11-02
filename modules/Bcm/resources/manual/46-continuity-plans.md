---
title: Continuity and recovery plans
audience: everyone
---

When something goes badly wrong — the head office is unreachable, ransomware encrypts the servers, a key supplier fails — people need to know what to do without improvising. **Risk & continuity → Continuity plans** holds two kinds of plan:

- a **business continuity plan (BCP)**: how the business keeps working — another site, manual workarounds, who decides;
- an **IT disaster recovery plan (DRP)**: how IT brings the systems back.

The continuity coordinator manages every plan and approves them; team leads write the IT recovery plans; agents read them, to use them during incidents. Each plan has an **owner** and, optionally, a **deputy**, who keep it up to date. Owners who are not IT staff find their plans under **My plans**.

## Writing a plan

Open the plan and click **Write the plan** (or **Start a review** once a version is approved). Then:

1. **What the plan covers:** the business processes from the business impact analysis and the IT systems from the CMDB.
2. **Scenarios covered**, the **objective**, **when to activate the plan** and **who declares the crisis**.
3. The **recovery strategy**, the **communication** (who is told what, when and how — with fallback channels in case e-mail is down) and the **assumptions**.
4. The **crisis team**: each role and who fills it, a person of the organization or an outside contact (phone, e-mail). Add a deputy for every key role.
5. The **runbook**: the steps in order, by phase — activation, recovery, resumption, return to normal — with who does it, roughly how long it takes and the system concerned. Reorder steps with **Move up** and **Move down**.

At the top of the page, the plan compares how long activation and recovery take with the **strictest RTO** of the processes it covers, and says whether it fits.

**Save draft** keeps the texts and scenarios (the scope, team and steps are saved as you add them). **Submit for approval** sends the plan to the continuity coordinator, who approves it or returns it with a comment. Nobody approves a plan they own or submitted. Once approved, the plan is in force until its review, usually a year later; a review starts from a copy of the version in force.

## Keep a copy offline

A crisis may take this application down too. On an approved plan, **Print the plan** shows the version in force on one page — scope, activation, crisis team with contacts, the runbook with a "done" column. Print it, or **Download PDF** where the PDF service is installed, and keep a copy where the crisis team can reach it.
