# ADR 0015 — Email-to-ticket over IMAP

- **Status:** Accepted
- **Date:** 2026-11-07

## Context

People write to a support address. Those messages, and replies to ticket emails, should reach tickets without anyone copying them. The owner's mail server is on-premises Exchange, reachable over IMAP with a username and password. Mail is untrusted input: it can be forged, automated, huge, or hostile, and a careless implementation creates mail loops with auto-replies.

## Decision

- **IMAP polling** with `directorytree/imapengine` (MIT; actively maintained; same author as LdapRecord; no `ext-imap`, which PHP 8.4 removed). `webklex/php-imap` was considered but had no release since April 2025. The library sits behind `MailboxReader`, so tests use a fake and another transport can be added later.
- **Mailboxes** (`ticketing_mailboxes`) are configured by administrators only (`ticketing.mailbox.manage`). The password is encrypted with the application key, hidden from serialization and the audit trail, never sent back to the browser; `credentials_updated_at` is audited instead. Errors are stored as short reason codes, never the server's text, which may echo credentials.
- **SSRF guard:** a mailbox connects only to allowed ports (993 and 143 by default), never to loopback, link-local (cloud metadata), unspecified, reserved or multicast addresses, and only encrypted unless the installation allows otherwise (development). Private networks stay allowed: on-premises Exchange lives there. Certificates are verified.
- **Reading:** the scheduler queues one `FetchMailbox` job per active mailbox every minute; the job is unique per mailbox. Messages are fetched without setting `\Seen`, handled, then marked as read (and moved, when configured).
- **Idempotency:** `ticketing_inbound_emails` logs each message by the hash of its Message-ID, unique per mailbox for every outcome except `failed`, so a message is handled once and a failed one can be retried. The log holds addresses and subjects and is pruned after 90 days (GDPR storage limitation).
- **Who:** only active users of the mailbox's organization reach a ticket. Unknown senders are ignored and logged, never answered (owner's decision): an answer would confirm the address to spammers and could feed loops.
- **Ignored as automatic:** RFC 3834 `Auto-Submitted`, `Precedence: bulk/junk/list`, Exchange's `X-Auto-Response-Suppress`, mailing list headers, bounces and delivery reports, and mail from our own addresses. Ticket emails carry `Auto-Submitted: auto-generated` and `X-Auto-Response-Suppress: All`, so Exchange does not send out-of-office replies to them.
- **Forgery:** when the receiving server recorded a failed SPF or DMARC check (`Authentication-Results`), the message is ignored. Since the security review of 2026-11-03 (owner's decision: strict for staff), a message from someone who may work the ticket (IT staff) without a DMARC **pass**, or an SPF **pass** for the domain of the From address (SPF concerns the envelope sender, which an attacker can set to a domain of their own), becomes an internal note, marked as unverified, with internal attachments; a new ticket in their name starts with the same warning. A mailbox can require a pass from every sender (`require_verified_senders`). `TICKETING_MAIL_TRUSTED_AUTHSERV_IDS` limits which servers' `Authentication-Results` are believed. A sender also needs the right to comment on the ticket they reply to (the ticket policy, as in the portal), and a per-sender hourly limit stops floods.
- **Threading:** the ticket number in the subject (`[INC-000123]`), looked up in the mailbox's organization. Ticket emails get the mailbox as Reply-To and a marker line; the reply is cut at the marker or at common quote headers. A reply to a closed ticket, or to one the sender cannot see, opens a new ticket.
- **Content:** plain text only. HTML is converted to text through DOM parsing with network access off; nothing of the markup is stored. Attachments go through the same checks as uploads (`StoreAttachment::assertAcceptable`), refused ones are listed in the ticket, inline images are skipped.
- **All or nothing:** each message is handled in one transaction; ticket events are dispatched after commit, so a failure leaves no half-made ticket and sends no email.

## Alternatives considered

- **Inbound webhooks** (a mail service posting messages): needs an external service, which an on-premises installation may not have.
- **Creating accounts or quarantining unknown senders:** more permissive, but the owner chose to ignore them.
- **Matching `In-Reply-To` against our Message-IDs:** more robust than the subject, but needs storing every outgoing Message-ID; the subject token is enough for now.

## Consequences

- Some hosted mail services need OAuth2 (XOAUTH2), which ImapEngine supports; adding it means storing a client registration instead of a password.
- An attacker who can forge the From address of a user, on a server that does not check SPF/DMARC, can raise tickets in their name. Exchange should reject forged internal senders from outside.
