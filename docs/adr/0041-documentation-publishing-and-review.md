# ADR 0041 — Documentation: publishing, approval and review

- **Status:** Accepted
- **Date:** 2026-12-04

## Context

Procedures and policies must be approved before people follow them, and kept current: ISO/IEC 27001 asks that documented information be approved for suitability (7.5.2) and that operating procedures be available and maintained (5.37). The owner chose a per-space setting: free spaces publish at once; controlled spaces need a space owner's approval. Every page gets a review date.

## Decision

- **Two texts per page:**
  - the **latest version** (`title`, `body`, `version`) is what editors work on;
  - the **published version** (`published_title`, `published_body`, `published_version`, `published_at`) is what readers see, what search indexes (the generated `tsvector` now covers the published text only), and what is printed.

  Pages never published are visible only to the space's editors. Existing pages were published as they were.
- **Free spaces:** every save is published at once.
- **Controlled spaces** (`requires_approval`):
  1. An editor **submits** the latest version (`pending_version`, `submitted_by`, `submitted_at`); the space's owners who may approve it are emailed.
  2. A **space owner** (or documentation manager) **other than the version's author and the submitter** approves it, which publishes it; the approver and time are kept on the version.
  3. Alternatively the owner rejects it with a reason, shown to editors and emailed to the submitter.
  4. Saving a newer version withdraws a pending submission, so nobody approves stale text.
- **History in controlled spaces** is for editors, since it contains drafts. In free spaces every version was published, so readers may see the history.
- **Review:**
  - each space sets a review interval (1–60 months, default 12);
  - publishing sets the page's next review date, and **Mark as reviewed** moves it on;
  - the space page lists pages to approve and pages due within 30 days or overdue;
  - `documentation:digest` (Mondays 08:20) emails each space owner the counts, and only when there is something to do.
- **Print and PDF:** the published version with its version, author, approver, approval date and review date, through Core's `PdfDownload` (throttled, audited).

## Alternatives considered

- **Readers see the latest version with a "draft" badge:** simpler, but it publishes unapproved procedures.
- **Approval by any owner including the author:** fewer steps, but no separation of duties.
- **A separate draft table:** the versions table already holds every state; two columns of text on the page are enough to serve readers and search.

## Consequences

- Turning approval on for a space keeps what is published; later saves wait for approval. Turning it off publishes at the next save.
- Part C serves "everyone" spaces' published pages to employees in the portal.
