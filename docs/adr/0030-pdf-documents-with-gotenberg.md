# ADR 0030 — PDF documents through Gotenberg

- **Status:** Accepted
- **Date:** 2026-11-23

## Context

CLAUDE.md §9 asks for a PDF export of an application's full documentation for audit; other documents (BIA sheet, continuity plans, exercise reports, asset handover protocols) are printable pages today, saved as PDF from the browser. The application sheet has generated diagrams (SVG), uploaded diagram images and wide tables, and should look in the PDF as it does on screen. The library must be compatible with AGPLv3.

## Decision (owner's choice)

- **Gotenberg** (MIT), a separate Docker container running headless Chromium, renders PDFs. The toolkit posts a **self-contained HTML document** — the compiled application stylesheet inlined, diagram images as data URIs — to `/forms/chromium/convert/html` through Laravel's HTTP client (`Modules\Core\Application\Pdf\PdfRenderer`). No Composer package is added.
- **Optional:** `GOTENBERG_URL` empty means no PDF button; the printable page and the browser's *Save as PDF* remain. Development: `COMPOSE_PROFILES=pdf` starts `gotenberg/gotenberg:8.39.0`.
- **Locked down:** JavaScript disabled in Chromium (`--chromium-disable-javascript`); every network fetch refused (`--chromium-deny-list` for `file:` outside its temp folder and for `http(s)`, `ftp`, `ws(s)`); LibreOffice routes disabled. Since the security review of 2026-11-03 also `--chromium-deny-private-ips` (a second barrier should the deny list ever be relaxed), `--api-disable-download-from` and `--webhook-disable`: Gotenberg has no authentication, so features that fetch or post to a given URL would make it an internal HTTP client for anything that reaches it. The container is reachable on the internal network only. A document can therefore never make Chromium reach internal services (SSRF) or run script from user content.
- **First use:** the application documentation (`/applications/sheets/{id}/pdf`, throttled, recorded in the audit trail as an export). Other printable documents can follow the same path. Since then: the risk report, the compliance report and Statements of Applicability, the BIA sheet, continuity plans and exercise reports, all through Core's `PdfDownload` (rendering, audit record, fallback to the printable page) and the `x-core::printable` component (page or self-contained document).

## Alternatives considered

- **Browsershot** (MIT): the same Chromium inside the application container with Node.js — a much larger application image and Chromium running next to the application, with less isolation.
- **dompdf** (LGPL-2.1): pure PHP, but CSS 2.1 only and weak SVG support: separate templates for PDF, slow on large documents.
- **mPDF**: GPL-2.0-only, **incompatible with AGPLv3** — excluded.
- **TCPDF** (LGPL-3.0): drawing PDFs in code is impractical for a 16-chapter document.

## Consequences

- One more optional container to run in production (`docker run gotenberg/gotenberg:8.39.0 …` with the same flags); PDFs need no fonts or browsers on the application server.
- Documents rendered to PDF must stay self-contained; anything they reference by URL is silently left out.
- The compiled stylesheet is inlined with `{!! !!}`: it is a build artefact, never user content (`</` is escaped).
