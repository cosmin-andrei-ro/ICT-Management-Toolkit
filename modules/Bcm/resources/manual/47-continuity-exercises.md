---
title: Exercises and recovery tests
audience: everyone
---

A plan nobody has tried is only a hope. Exercises show whether the people, the contacts and the steps work, and how long recovery really takes. They are what auditors ask for (ISO 22301, NIS2, DORA).

Whoever keeps a plan up to date — its owner and deputy, the continuity coordinator, and team leads for IT recovery plans — runs its exercises. Everyone who can see the plan can follow them.

## Plan an exercise

On a plan with an approved version, click **Plan an exercise** under **Exercises and tests** and choose the kind:

- **Tabletop exercise:** the team talks through the scenario and the plan around a table; nothing is switched off.
- **Walkthrough:** each step is checked where it happens (access, contacts, documents), without recovering anything.
- **Technical recovery test:** systems are actually restored, in a test environment or the recovery site, while production keeps running.
- **Full interruption test:** production is switched to the recovery arrangements for real. Plan it as a change in Ticketing too.

Add a title, the scenario, the date, the facilitator and the participants.

## Run it

When the exercise begins, click **Start the exercise**. The runbook of the version in force is copied into the exercise, so a later version of the plan never changes what was tested.

For each step, record the result — **Done**, **Done, with issues**, **Failed** or **Skipped** — how long it took and any notes. Under **Systems to bring back**, mark each system **Recovered** or **Not recovered** and how long it took to be back. **Save results** as you go.

The page adds up the activation and recovery steps and compares the time measured with the plan's estimate and with the strictest RTO of the processes the plan covers.

## Findings

Write down everything that has to be fixed — a missing backup, a wrong phone number, a step nobody knew how to do — with its severity and, if it applies, the step. **Raise a problem** sends it to IT support as a problem, with the system of the step linked; the page then shows the problem number and its status. A problem can be raised only once per finding, also after the exercise is completed.

## Complete it

Every step needs a result. Choose the **outcome** (successful, partly successful, failed), write a summary and the lessons learned, and click **Complete**. The exercise is then kept as evidence and can no longer be changed; **Report** shows the exercise on one page - steps and results, systems recovered, time against the RTO, findings and lessons - to print, or to **Download PDF** where the PDF service is installed.

On completion:

- the plan shows the date it was **last exercised**;
- each system marked as recovered shows, on its CMDB page, **Recovery last tested** with the date, the time it took and the exercise. If the test took longer than the recovery time IT declared, the page says so — update the declared value if the test was representative.

An exercise that will not happen can be cancelled with **Cancel the exercise**.
