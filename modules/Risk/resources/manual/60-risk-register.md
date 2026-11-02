---
title: Risk register
audience: everyone
---

**Risk & continuity → Risk register** lists what could go wrong, what it would affect, and how likely and how bad it would be. Risk managers keep the register; each risk has an **owner** who assesses it. Owners who do not read the whole register find their risks under **My risks**.

## Register a risk

Risk managers click **New risk** and describe the scenario - what could happen and its consequence, for example *Ransomware encrypts the payroll database* - with its category and owner. Risks are numbered R-0001, R-0002…

## Suggested risks

The toolkit points risk managers to situations that call for a risk, under **Suggested risks** on the register:

- **applications**: personal or payment card data processed, reachable from the internet without multifactor authentication, certificates expiring, components past their end of support - as soon as an application's documentation comes into force;
- **continuity**: systems that cannot be recovered as fast as the business needs, critical processes depending on a supplier, processes without a plan in force, plans that would be too slow;
- **operations**: systems with three incidents or more in 90 days, licences expiring, equipment out of warranty running important systems;
- **suppliers**: contracts ending soon that do not renew;
- **compliance**: major nonconformities not yet closed, frameworks with applicable requirements no control meets.

For each, choose the owner and **Register the risk**: it is created with the scenario, what it concerns, the threats and vulnerabilities and, where known, the scores - then assessed and treated as any other. Or **Dismiss** it, saying why; it will not be suggested again while the situation lasts. Suggestions whose situation is gone move to *No longer applies*. The list is checked every morning; **Check now** checks at once.

## Assess it

On the risk page, the owner (or a risk manager):

1. **Links what it concerns**: applications of the portfolio, CMDB items, business processes of the business impact analysis, suppliers. Choose the kind and search by name.
2. Ticks the **threats** and **vulnerabilities** involved, from the organization's catalog.
3. Describes the **controls already in place**.
4. Scores **likelihood** and **impact** from 1 to 5; the meaning of each level is shown under the list.

The risk's level - low, medium, high or critical - follows from likelihood × impact and the organization's bands. This is the risk **before treatment**; treatment and the residual risk come next.

## Treat it

Under **Treatment**, the owner chooses how the risk is handled - **mitigate** (add or improve controls), **transfer** (insurance, contract), **avoid** (stop the activity) or **accept** - describes the plan, and scores the **residual risk**: the level expected once the treatment is in place. The register shows both levels.

**Treatment actions** carry the plan out: each has an owner and a due date, and overdue ones are shown in red. Mark them started, done or cancelled; **Track in the service desk** opens a problem for IT with the risk's CMDB items.

## Accept the residual risk

Once the residual risk is scored, the owner explains why it is acceptable:

- below the organization's threshold, the owner accepts it at once;
- from the threshold, the request goes to the risk managers. A risk manager other than the owner and the person who asked approves it or rejects it with a reason.

An acceptance lasts until the next reassessment. If the residual risk changes, the acceptance no longer applies and must be asked again.

## Reassess it

Risks are reassessed periodically (yearly by default). About a month before, the owner gets an e-mail. Reviewing the scores and controls and clicking **Save the assessment** counts as the reassessment and sets the next one. **Score history** shows how the inherent and residual levels evolved.

## The heat map

The register shows open risks on a 5 × 5 grid: likelihood from bottom to top, impact from left to right, coloured by level. Click a cell to list its risks; click it again to show them all.

## Reports

- The heat map shows the **current** risk (after treatment where the residual risk is assessed) or the risk **before treatment**.
- **Risk report** lays out, for management or auditors, the method, both heat maps, the number of risks per level, and every open risk with what it concerns, its treatment and actions. Print it, or **Download PDF** when the PDF service is set up.
- **Export to Excel** gives the register, the treatment actions and the acceptances.
- Each application's page lists the open risks that concern it.
- Every Monday, risk managers get an e-mail with what needs attention: suggestions to review, acceptances waiting or expiring, overdue actions and reassessments.

## The method and the catalog

Risk managers adjust, in **Administration → Risk**:

- **Risk method**: what each likelihood and impact level means, where the levels start, from which score accepting a risk needs a risk manager, and how often risks are reassessed. Changing the bands re-levels every risk at once.
- **Threats and vulnerabilities**: the catalog risks are built from. An entry used by a risk can be deactivated but not deleted.
