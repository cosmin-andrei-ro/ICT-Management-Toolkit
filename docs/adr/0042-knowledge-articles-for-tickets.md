# ADR 0042 — Knowledge articles for tickets

- **Status:** Accepted
- **Date:** 2026-12-05

## Context

A knowledge base pays off when people find an answer before raising a ticket, and when agents find the procedure while working one. The owner wants articles for employees in the portal, suggested when a ticket is raised. Ticketing must not read Documentation's tables (CLAUDE.md §5.1).

## Decision

- **A Core contract, `KnowledgeArticles`**, implemented by the Documentation module, with `NoKnowledgeArticles` bound while it is disabled. It has two methods:
  - `suggest(text, viewer)`;
  - `about(type, id, viewer)`, the pages linked to a record.

  Both return `LinkedRecord`s of **published pages of spaces the viewer reads**, so drafts and spaces the viewer may not read never surface.
- **Suggestions** match **any** word of the text of three letters or more (prefix match, at most ten words), ranked by full-text relevance. A ticket title rarely contains every word of the article, so requiring all of them, as the documentation search does, would find too little. Only letters and digits reach the query, so no full-text operator can be injected.
- **Portal:** suggestions appear under the title while a request is written, and open in a new tab. Requesters' tickets show the articles linked to them that they may read.
- **Ticketing:** the ticket page shows the linked articles and other suggestions from its title. Known errors are problem tickets and show their articles the same way.
- **Linking** stays on the page side (*About → Ticket*), where editing rights are checked. Ticketing writes nothing into Documentation.
- **Documentation pages as records of `LinkedRecords`** (`documentation.page`, resolve and search, published and readable only). Other modules can now link them, for example a procedure as evidence of a compliance control.

## Alternatives considered

- **Ticketing querying the pages itself:** faster to write, but it couples the modules and duplicates the reading rules.
- **Suggestions with the same all-words search:** precise, but rarely matches a free-text title.
- **Stemmed search (English configuration):** better for plurals, worse for Romanian or mixed text; the prefix match covers the common cases.

## Consequences

- An organization without the Documentation module sees no suggestions and no empty panels in the portal.
