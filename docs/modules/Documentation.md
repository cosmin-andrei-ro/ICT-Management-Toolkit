# Documentation module

Procedures, how-tos and reference pages, organised in spaces. Code: `modules/Documentation/`. Decisions: [ADR 0040](../adr/0040-documentation-spaces-and-pages.md).

## Concepts

- **Space** (`documentation_spaces`): name, description, visibility (`staff`, `everyone`, `teams`), `requires_approval` (part B), owners (`documentation_space_owners`), teams that read or edit (`documentation_space_teams`, `read` / `edit`). Soft deleted.
- **Page** (`documentation_pages`): space, parent page, title, Markdown text, current version, position; `search_vector` (generated, GIN). Soft deleted; sub-pages move up.
- **Version** (`documentation_page_versions`): every saved state: title, text, what changed, author, time.
- **Link** (`documentation_page_links`): a record of another module (`linked_type`, `linked_id`), title kept.
- **Files**: Core attachments on the page.

`SpaceAccess` decides who reads (`readable()`, also used by search) and who edits (`editable()`).

## Screens

- `/docs`: spaces the person reads, recently changed pages, search across them (full text, highlighted extracts).
- `/docs/spaces/new`, `/docs/spaces/{id}/edit`: name, visibility, teams that read or write, owners.
- `/docs/spaces/{id}`: the page tree; search in the space; new page.
- `/docs/pages/{id}`: the page, its place, its sub-pages, what it is about (links) and its files.
- `/docs/spaces/{id}/pages/new[?parent=]`, `/docs/pages/{id}/edit`: Markdown with toolbar and live preview; refused when someone saved in between.
- `/docs/pages/{id}/history[?version=]`: versions, line diff against the previous one, restore.

## Permissions

| Permission | Default roles | Purpose |
|---|---|---|
| `documentation.staff.read` | every role but Requester | Read spaces for IT staff |
| `documentation.space.manage` | team-lead (and administrators) | Create spaces; read and write every space |

Owners change their space's settings and write in it; members of a space's editing teams write in it; members of its reading teams read it.

## Plan

1. Spaces, pages, versions, search, links and files — **done**.
2. Drafts and approval in controlled spaces, review dates with reminders, page PDF.
3. Articles for employees in the portal, suggested when raising a ticket; links with tickets and known errors.
4. Pages generated for applications when their documentation comes into force (CLAUDE.md §9), with free notes.
