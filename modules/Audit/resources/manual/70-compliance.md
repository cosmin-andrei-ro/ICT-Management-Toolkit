---
title: Compliance
audience: everyone
---

**Compliance & audit → Compliance** shows the regulations and standards your organization complies with, and how far its controls meet them. Compliance managers keep it; auditors, security officers and risk managers read it. Each control has an **owner** who says how far it is implemented; owners who do not read compliance find their controls under **My controls**.

## Add a framework

Compliance managers add frameworks at the bottom of the page:

- **From the toolkit**: ISO/IEC 27001:2022 Annex A, NIS2, GDPR and DORA, each with all its requirements. ISO/IEC 27001 controls come with their identifiers (5.1, 8.5…) and the toolkit's own short titles: the standard's text is protected by copyright, so if you want it shown, copy it from your licensed copy into each requirement. The descriptions of NIS2, GDPR and DORA are summaries, not the legal text.
- **Your own**: an internal policy, a customer's or a regulator's requirements. Add its requirements on its page, under **Add a requirement**, each with a reference and a title.

Each framework shows how many of its applicable requirements are **met**, and how many are in each state. A framework you no longer follow can be **archived** from its page; it is kept with its history.

## Decide what applies

On a framework's page, **Decide** next to a requirement: **applicable**, **not applicable** or not decided yet. A requirement that does not apply needs a **justification**, for example *No premises of our own: offices in a shared building secured by the landlord*. For ISO/IEC 27001, the decisions and justifications make up the **Statement of Applicability**. You can also enter the requirement's wording or your own guidance.

## Controls

**Controls** lists what the organization does to meet its requirements, numbered C-001, C-002… Compliance managers click **New control** and give it a name, for example *Multifactor authentication on remote access*, a description, an owner and a status: planned, partly implemented, implemented or retired.

On the control's page, under **Requirements it meets**, choose a framework, search its requirements by reference or title, and **Map** them. One control usually meets requirements of several frameworks - multifactor authentication meets ISO/IEC 27001 8.5, NIS2 Art. 21(2)(j) and DORA Art. 9 - so keep it once and map it everywhere. **Not mapped to any requirement** in the list shows the controls still to map.

## Keep your controls up to date

The owner of a control - or a compliance manager - sets its **status** and describes **how it is implemented** on the control's page. A requirement's state follows its controls:

| State | Meaning |
|---|---|
| Applicability not decided | Nobody has decided yet whether it applies, and no control meets it. |
| Not applicable | Decided not to apply, with a justification. |
| No control | It applies, but no control meets it. |
| Controls planned | Its controls are only planned. |
| Partly met | Some of its controls are implemented or partly implemented. |
| Met | All its controls are implemented. |

Retired controls no longer count.

## Evidence

Under **Evidence** on the control's page, the owner or a compliance manager shows that the control works:

- **Link a record of the toolkit**: choose the kind - risk, application, continuity plan, exercise or recovery test, business process, contract, service desk ticket - search it by name or number and **Link** it, saying what it shows. Only records you may open are offered. People who may not open a record still see its title.
- **Upload a document**: a report, a configuration export, a signed policy. Give it a title and, if it expires (a certificate, a yearly report), the date it is **valid until**; expired documents are shown in red.

Auditors and everyone who reads compliance see the evidence and download the documents. **Remove** takes evidence off the control; it stays in the history.

## Tests

A compliance manager sets how often a control is tested, in months, when editing it. Its owner is emailed two weeks before each test is due; the controls list shows the **next test**, overdue ones in red, and **Test overdue** lists them.

To **Record a test**, give the date, the result - effective, partly effective, not effective - **how it was tested** (for example *a sample of 25 remote sign-ins checked for a second factor*), what was found and, optionally, the document the test produced. The next test is planned from the date of the test.

Every change - to frameworks, applicability, controls and their mapping - is kept in the history.
