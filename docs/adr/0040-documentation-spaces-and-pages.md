# ADR 0040 — Documentation: spaces, pages and versions

- **Status:** Accepted
- **Date:** 2026-12-03

## Context

CLAUDE.md §3 plans a Documentation / Knowledge Base module: versioned pages, templates, spaces, approvals, links to any entity, PDF export. ISO/IEC 27001 Annex A 5.37 asks for documented operating procedures available to those who need them. IT teams keep procedures, how-tos and reference pages; employees need simple guides. The toolkit already links records across modules (LinkedRecords) and stores files (attachments).

## Decision (owner's choices)

- **Module `Documentation`** (`documentation_*` tables), part A of four:
  - A: spaces and pages;
  - B: approval, review and PDF;
  - C: portal and service desk;
  - D: generated application pages.
- **Spaces** (`documentation_spaces`) group pages. Each space has a name, owners (people), the teams that write in it, and a **visibility**:
  - **IT staff**: people holding any role beyond Requester, through `documentation.staff.read`;
  - **everyone** in the organization;
  - **chosen teams**.

  `documentation.space.manage` (team leads, administrators) creates spaces and reads and writes all of them. Owners change their space's settings; members of editing teams write pages. A space's `requires_approval` setting is prepared for part B.
- **Pages** (`documentation_pages`):
  - kept in a tree (parent page, position), written in **Markdown** with a toolbar and a live preview;
  - rendered by Core's `Markdown::toSafeHtml()` (GitHub-flavoured, raw HTML stripped, unsafe link schemes dropped), now shared with the user manual;
  - the most a page may hold is 200,000 characters.
- **Versions:** every save that changes something is a new version (`documentation_page_versions`, with an optional "what changed"), so the full text history stays.
  - Saving over a version newer than the one opened is refused (optimistic locking under a row lock), so no change is lost silently.
  - History shows a line diff (Myers, written here: no new dependency) and can restore a version as a new one.
  - The audit trail records titles, places and deletions but leaves the text to the versions.
- **Deleting** a page is a soft delete; its sub-pages move up to its parent.
- **Search:** PostgreSQL full-text search on a generated `tsvector` (title weighted above text, `simple` configuration for mixed-language text, GIN index), queried with `websearch_to_tsquery`. It only covers spaces the person reads, and highlights extracts that are escaped before marking.
- **Links:** links to records of other modules go through Core's `LinkedRecords` (search and resolve), as the editor sees them, with the title kept for readers who cannot open the record.
- **Files:** pages carry files through Core's attachments, downloadable by the page's readers.

## Alternatives considered

- **WYSIWYG editor:** friendlier for some, but it needs an npm package, HTML sanitizing of stored content, and gives unreadable diffs.
- **One permission per action instead of spaces:** coarse; documentation access follows teams and areas, not roles alone.
- **Storing only the latest text with the audit trail as history:** the audit trail would hold large texts in JSON, and diffs and restoring would be awkward.

## Consequences

- Readers see the latest saved version for now. Part B adds drafts and approval in controlled spaces, review dates and PDF; part C opens "everyone" spaces in the portal and suggests articles when raising a ticket; part D generates application pages (CLAUDE.md §9).
