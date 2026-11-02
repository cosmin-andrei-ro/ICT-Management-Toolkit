# ADR 0043 — Documentation pages generated for applications

- **Status:** Accepted
- **Date:** 2026-12-05

## Context

CLAUDE.md §9: approving a new application's documentation generates or links, among others, a page in Documentation. The owner chose a generated summary replaced at every approved version, with free notes kept by the team. Applications and Documentation must stay apart (CLAUDE.md §5.1).

## Decision

- **Core domain event `ApplicationDocumented`**, dispatched by Applications when a version of the documentation comes into force, inside the approval's transaction. It carries the summary already resolved:
  - code, name, version, description, business criticality label;
  - owners (role labels and names, and their ids);
  - support (vendor, portal, e-mail, support hours);
  - CMDB component names;
  - business processes with formatted RTO and RPO;
  - links to the application and to its full documentation.

  Documentation listens. Applications knows nothing of pages, and Documentation knows nothing of sheets.
- **The "Applications" space:** a space with `purpose = applications` (unique per organization) is created on first use, read by IT staff. A space the organization already named "Applications" is adopted instead of duplicated. The application's owners and the approver become owners of the space, so the team can write its notes.
- **One page per application**, found by its link to the application (`documentation_page_links`), titled "CODE · Name". Its text has two parts:
  1. the **summary**, between two HTML comment markers, which the safe Markdown renderer strips from the output;
  2. the **Notes** section below them.

  At every version only the text between the markers is replaced. If the markers were removed by hand, the summary goes back on top and the rest is kept. Each regeneration is a normal page version ("Summary from documentation version X"). An unchanged summary makes no version.
- **Publishing:** the summary was approved with the documentation, so it is **published at once even if the space requires approval**. Changes to the notes follow the space's rules.
- **Markdown:** text from the documentation is put on one line with its Markdown signs escaped, so it shows literally and cannot break the tables.
- **Links:**
  - the page is linked to the application, and the application page shows its documentation pages through `KnowledgeArticles::about()`;
  - the page is linked to the application's CMDB item through `ConfigurationItems::link()`, so the item's page lists it.

## Alternatives considered

- **The page reads the sheet live:** always current, but it couples the modules and cannot keep the team's notes in one versioned page.
- **Two pages (summary and notes):** clean separation, but readers look in one place.
- **Asking approval for each regenerated summary:** a second approval of content already approved.

## Consequences

- Editing the summary by hand is undone at the next version; the generated block says so.
- With Documentation disabled, the event has no listener and approval works as before.
