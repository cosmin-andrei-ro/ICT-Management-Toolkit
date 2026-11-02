---
title: Documentation
audience: everyone
---

**Documentation** in the menu gathers procedures, how-tos and reference pages, organised in **spaces** - for example *Network*, *Service desk procedures*, *Guides for employees*. You see the spaces you may read; search finds words in their pages, with the matching passages highlighted.

## Who reads and who writes

Each space says who reads it:

- **IT staff** - everyone with an IT role;
- **Everyone** - all employees too;
- **Chosen teams** - members of the teams picked.

Its **owners** and the members of its **teams that write** create and change pages. Team leads and administrators create spaces: **New space**, with the name, who reads it, the teams that write in it and the owners.

## Writing a page

In a space, click **New page** (or **New sub-page** on a page, to put one under it). Pages are written in **Markdown**, a simple way to format text:

| You type | You get |
|---|---|
| `## Restart the VPN` | a heading |
| `**important**` | **important** |
| `- first step` | a bulleted list |
| `1. first step` | a numbered list |
| `[the portal](https://…)` | a link |
| `` `ipconfig /all` `` | a command |

The buttons above the text do it for you, and the **preview** next to it shows the page as readers will see it. HTML is not allowed. Say in **What changed** what you did, then **Save**.

If someone else saved the page while you were writing, your save is refused rather than overwriting their work: copy your text, open the page again and add your changes.

## Approval in controlled spaces

Spaces for procedures and policies can require **approval**: their owners tick **Pages need approval** and set how often pages are **reviewed**.

In such a space, saving does not change what readers see. A page never published is visible only to the space's editors; a newer version of a published page is a **draft** until approved, and readers keep seeing the published one. Editors switch between the two with **See the latest version** / **See the published version**.

1. When the text is ready, click **Submit for approval**. The space's owners are emailed.
2. An owner - not the one who wrote the version, nor the one who submitted it - opens the page, checks **See the changes**, and clicks **Approve and publish**, or **Reject** saying why. The submitter is emailed either way.
3. Saving again after submitting withdraws the submission: submit the new version.

The page then shows which version is published and who approved it. In controlled spaces the history is shown to editors only.

## Review

Every published page has a **review** date, from the space's interval. On the space's page, editors see the pages **to review** (overdue in red) and owners the pages **waiting for approval**. When a page is still right, **Mark as reviewed** moves its next review on; otherwise edit it. Every Monday, space owners receive an email with what waits for them.

## Print and PDF

**Print or PDF** shows the published version with its version number, author, approver and review date, to print or - where the PDF service is installed - to download as PDF.

## About and files

On a page, **Link a record** connects it to what it is about - an application, a CMDB item, a continuity plan, a risk, a contract, a ticket: choose the kind, search, **Link**. People who may not open the record still see its name. **Files** keeps diagrams, exports or manuals with the page; whoever reads the page can download them.

## Documentation and the service desk

Published pages of spaces read by **everyone** are the employees' help: they find them in **Documentation** and, while writing a new ticket, as **suggestions** under its summary. Link a page to a ticket or a known error (**Link a record → Service desk ticket**) and it shows on the ticket's page - to agents always, to the requester when they may read it. Other modules can link pages too, for example a procedure as evidence of a compliance control.

## Application pages

When an application's documentation comes into force, the toolkit writes its page in the **Applications** space: a summary of the documentation - owners, support, components, business processes with their recovery objectives - and a link to the full documentation. At every new version the summary is replaced; the **Notes** below it are yours and stay as they are. To change the summary, change the application's documentation, not the page. The page also shows on the application and on its CMDB item.

## History

**History** lists every saved version, who saved it and what changed. Choose one to see the lines added (in green) and removed (in red) compared with the version before. **Restore this version** makes an old text current again - as a new version, so nothing is lost.

Deleting a page keeps its history; its sub-pages move up one level.
