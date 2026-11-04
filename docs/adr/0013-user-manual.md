# ADR 0013 — In-app user manual, contributed by modules

- **Status:** Accepted
- **Date:** 2026-11-05

## Context

People using the platform (requesters, IT support agents, administrators) need a manual reachable from the application. Each module adds features, so the manual must grow with the modules without a central file every module edits.

## Decision

- **Pages are Markdown files shipped by each module** in `modules/<Module>/resources/manual/`, registered by the module's provider into `Modules\Core\Domain\Manual\Manual`. A module that is disabled takes its chapters with it.
- **Front matter** sets the title and the audience: `everyone`, `agents` or `administrators`. The file name (`10-tickets.md`) sets the order and the URL (`/manual/tickets`). Malformed pages throw, and a test loads every page, so CI catches mistakes.
- **English**, like the application (owner's decision), so the manual names buttons exactly as people see them.
- **Public** (owner's decision): `/manual` needs no sign-in, because the people who cannot sign in need it most. It contains no organization data.
- **Rendering:** CommonMark (GFM tables) via `Str::markdown` with `html_input: strip` and `allow_unsafe_links: false`. This is the "explicitly sanitized" case that `CLAUDE.md` §6 allows for `{!! !!}`, and a test asserts that `<script>` and `javascript:` links are removed.
- **Entry points:**
  - a **User manual** button in the application header;
  - **Help with signing in** under the login form;
  - a **User manual** item in the administration panel, opening in a new tab.
- **Definition of Done** (`CLAUDE.md` §8) now includes updating the manual for every user-visible feature, and never describing features that do not exist yet.

## Rejected alternatives

- **An external wiki or documentation site:** it drifts from the installed version, and self-hosted installations may not reach it.
- **Pages stored in the database:** not versioned with the code that they describe.

## Consequences

- Each stage adds or updates its module's chapters in the same pull request as the feature.
- A searchable manual and per-organization notes can come later, on the same page model.
