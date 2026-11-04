# ADR 0021 — Assets: import, export, handover protocol and QR labels

- **Status:** Accepted
- **Date:** 2026-11-13

## Context

An organization starts asset management with an inventory it already has, usually a spreadsheet, and must be able to take the register out again. When equipment is handed to someone, both sides need a record of what was handed over (ISO 27001 A.5.9 inventory, A.5.11 return of assets), and labels on the devices make every later check faster.

## Decision

- **Import** (`/assets/import`, for `itam.asset.manage`) follows the CMDB's: CSV or XLSX, recognized by content, at most 2,000 rows and 5 MB; a preview that saves nothing; the file read again on import; every row or none.
  - A row is matched by **asset tag**, or by **serial number when it has no tag**. A new tag with a known serial number is reported as an error rather than taken as a relabel.
  - A **holder** column (email) hands the asset to that person through the same action as the asset page, without emailing them: the inventory describes equipment people already have.
  - The status of an existing asset is not changed by import; it changes on its page, through the life cycle.
  - The shared pieces moved to Core: `ImportRow`, `ImportHasErrors` and `SpreadsheetReader::readUpload()` (format by content).
- **Export** (XLSX or CSV, through Core exports, for `itam.asset.manage`): the whole register with the import's columns first, so it can be exported, corrected and imported back; status, contract and disposal columns follow, and attribute columns of every category last.
- **Handover protocol:** a printable page per assignment (`/assets/handovers/{id}`), for the holder and IT staff who see assets. It lists the equipment, who handed it over and who received it, a statement, signature lines, and the return when there is one. The browser prints it or saves it as PDF; no PDF library is added now (the choice is deferred to the application portfolio, which needs real PDF export).
- **Acknowledgement:** handing out from the asset page emails the holder (after the commit). The holder confirms receipt on **My equipment**; only their own open assignment, once; the time and IP address are stored, printed on the protocol and recorded on the asset's audit trail.
- **QR labels:** a printable A4 sheet (3 × 8) of the assets the list shows (same filters, at most 240), for `itam.asset.manage`. Each QR code holds only the address of the asset page, which requires sign-in and permission. Codes are drawn on the server as SVG data URLs by **chillerlan/php-qrcode** (MIT or Apache-2.0), already installed for Filament's two-factor authentication and now a direct dependency because we call it.
- **Printing** uses a `data-print` attribute and a small script, since the CSP forbids inline handlers (ADR 0006).

## Alternatives considered

- **PDF generated on the server (dompdf…)**: a document that looks the same everywhere, but a dependency to choose once for every module; postponed.
- **A signature drawn on screen**: heavier, and no stronger in law than a confirmation tied to the signed-in account with its time and address.
- **Matching rows by serial number first**: catches retagged devices, but silently changes labels; refused in favour of an explicit error.
- **QR codes holding the asset's details**: readable offline, but every label would expose them; the address reveals nothing.

## Consequences

- The register can move in and out of spreadsheets without loss; attribute columns follow the categories.
- Protocols exist for every hand-out, including imported ones (without the email); people confirm imported equipment when they next open My equipment.
- When a PDF library is chosen, the protocol can be offered as PDF without changing what it contains.
