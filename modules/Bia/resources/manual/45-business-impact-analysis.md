---
title: Business impact analysis
audience: everyone
---

A **business impact analysis** (BIA) answers, for each thing the business does, two questions: *how bad would it be if it stopped, and how fast must it be back?* IT then plans its recovery for those answers.

Each **business process** (payroll, invoicing, customer support…) has an **owner** in the business and, optionally, a **deputy**. They fill in its assessment; the **continuity coordinator** approves it.

## For process owners

**My processes** in the menu lists the processes you own or deputize for, and what is expected of you: an assessment to fill in, one returned to you, or a review due.

Open the process and click **Start the assessment** (or **Start a review** once one was approved). Then:

1. **Impact of an interruption over time.** For each kind of impact (financial, legal, reputation, operations, customers) and each period (4 hours, 1 day…), choose how bad it would be if the process stopped for that long, from 1 (negligible) to 5 (severe). Hover over a level, or open **What each level means**, to see what it means. Impact never goes down with time. Cells at or above the unacceptable level are shown in red.
2. **MTPD**, the maximum tolerable period of disruption, follows from your scores: the first period in which any impact becomes unacceptable. If it should be different, tick **Use another MTPD** and explain why.
3. **RTO**: how fast the process must work again. It cannot be longer than the MTPD.
4. **RPO**: how much recent data may be lost. 0 if none; 1 day if yesterday's backup is enough.
5. Describe the **minimum level of service** while recovering, the **manual workarounds** (paper forms, phone, another site) and the **minimum staff**.

6. Under **What the process needs**, add the IT systems it uses (search the CMDB), the suppliers, places and other processes it depends on, and the key roles (with how many people at least) and key people without whom it stops.

**Save draft** keeps your work and updates the MTPD; **Submit for approval** sends it to the coordinator. If it comes back, the coordinator's comment says what to change. Once approved, the objectives are in force until the next review, usually a year later. A month before, you receive an email reminding you to start the review.

## For the continuity coordinator

**Risk & continuity → Business impact analysis** lists every process with its tier, RTO, RPO and next review. **New process** adds one: name it, choose its department, its owner and, optionally, a deputy. A process no longer done is set to **Retired**, which keeps its history.

When an assessment is submitted you receive an email. Open it, check it, and **Approve** it or **Return to the owner** with a comment. You cannot approve a process you own or an assessment you submitted: another coordinator does.

The **tier** follows from the RTO: tier 1 within 4 hours, tier 2 within a day, tier 3 within 3 days, tier 4 later. Team leads see the BIA too, to plan IT recovery.

At the top of the list, the overview counts processes per tier, those never assessed or overdue for review, and the assessments being filled in or waiting for you. **Export to Excel** gives the whole register: processes, what they need, and the recovery gaps. Every Monday you receive an email when something needs attention.

On a process, **BIA sheet** shows its analysis on one page: print it, or save it as PDF from the print dialog, for an audit.

Every version stays in the history. Approved versions never change: a review starts a new version, copied from the one in force.

## Recovery gaps

**Risk & continuity → Recovery gaps** compares, for each IT system that approved processes use, the recovery the business requires with what IT declared it can deliver (on the system's CMDB page, under **Recovery capability**). A system needed by a process is also needed by the processes that use systems depending on it: the CMDB relationships carry the requirement along, and each system gets the strictest one.

- **Gap**: IT cannot bring the system back as fast, or would lose more data, than required.
- **Not declared**: IT has not said what it can deliver.
- **Met**: the capability is enough.

The BIA also suggests a criticality for each system from the tier of the processes behind it (tier 1 critical, tier 2 high, tier 3 medium, tier 4 low). People who may change CMDB items can **Apply** it.
