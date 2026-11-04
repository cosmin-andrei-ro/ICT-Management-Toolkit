# ADR 0073 — Antivirus scanning of uploads

- **Status:** Accepted
- **Date:** 2026-11-04

## Context

`CLAUDE.md` §6 names optional ClamAV scanning of uploads. Files people upload, or that arrive by email, are later opened by others. Checking the type from the content stops many attacks, but not a malicious document of an allowed type.

## Decision

- **Optional, off by default.** ClamAV needs about 2 GB of memory, so a small installation should not have to run it.
- **The toolkit talks to a ClamAV daemon directly,** over its documented INSTREAM protocol on a socket, with no new dependency. The address comes from the server's settings, never from a user.
- **At one place, `StoreAttachment::assertAcceptable`,** which every upload and email attachment passes through, after the type and size checks.
- **It fails closed.** Once it is on, a file that cannot be scanned is refused, and the reason is logged. An outage must not become a way past the scanner. For email, the ticket is still created, and the refused file is listed as usual.
- **A threat is logged** with the signature name and the file's hash, without the name a person gave the file.

## Alternatives considered

- **Failing open when the scanner is down:** uploads would keep working, but anyone able to overload the scanner could get past it.
- **Scanning later, in the queue:** uploads are faster, but a file would be downloadable before it was checked.
- **Rescanning stored files as signatures update:** catches older threats, but needs its own job and a way to quarantine files. Could come later.

## Consequences

- With the scanner down, nobody can upload, and email attachments are refused, until it is back. Operators should monitor it.
- Files larger than ClamAV's own stream limit (25 MB by default) get an error and are refused. The default attachment limit is 20 MB, so raising it means raising ClamAV's too.
- Each upload waits for the scan.
- Spreadsheets read by imports are not stored, and are not scanned.
- Files already stored are not scanned when the feature is turned on.
