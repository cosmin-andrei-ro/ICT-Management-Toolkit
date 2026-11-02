# ADR 0046 — Management overview and the monthly report

- **Status:** Accepted
- **Date:** 2026-12-08

## Context

The owner wants a dashboard for management that covers every module, and a monthly management report as a PDF. The report goes by email to subscribers chosen by an administrator and can be downloaded on demand. Managers often have no role in the modules themselves, and the report must read the same whenever it is reopened.

## Decision

- **Permission `reporting.management.view`** opens:
  - the **Management overview** (`/reporting/management`), with every indicator of every module (`Metrics::overview()`): current value, status, change over 30 days and the last 90 days as a line;
  - the **monthly report**.

  Both show **figures only**, so they need no permission on the modules. Links to an indicator's trend page are shown only to people with that indicator's permission, and the lists behind the figures keep their own authorization.

  No default role gets the permission. Administrators have it and grant it to the people who need it.
- **Monthly report** (`/reporting/report?month=Y-m`, `/reporting/report/pdf`):
  - for this month and the eleven before;
  - built **only from the daily values** (ADR 0045), so a past month always reads the same;
  - for each indicator: the last value of the month, the last value of the month before, the status at the end of the month and the last twelve months as a line;
  - a summary counts the indicators by status.

  It is printed through `x-core::printable`. The PDF goes through `PdfDownload` and is recorded as an export.
- **Subscriptions** (`reporting_report_subscriptions`, with an audit trail):
  - kept by people with `reporting.subscriptions.manage` (administrators by default), on the overview page;
  - only active members who may see the overview can be added.

  `reporting:monthly-report` runs on the first day of the month at 07:00, after the month's last snapshot. It emails each subscriber the counts of indicators needing action or attention and a link to the report (sign-in needed). Where the PDF service runs, it also attaches the PDF; `REPORTING_ATTACH_PDF=false` turns that off. At sending time, subscribers who lost the permission or their account are skipped.

## Alternatives considered

- **The overview filtered by each module's permission:** that is the personal dashboard already. Management needs the whole picture without being granted every module.
- **The report recomputed from live figures:** a past month could not be reproduced.
- **Link only, never an attachment:** safer if mail is forwarded, but the owner asked for the PDF by email. The report holds counts only, no records or personal data, and the attachment can be switched off.

## Consequences

- Granting `reporting.management.view` discloses every module's figures, though not their records. It is meant for management and auditors.
- The report of a month before the module was installed shows "—".
