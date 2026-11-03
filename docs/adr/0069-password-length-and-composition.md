# ADR 0069 — Password length and optional composition rules

- **Status:** Accepted
- **Date:** 2026-11-04

## Context

Passwords had to be 12 to 128 characters, with no composition rules (ADR 0057), following NIST SP 800-63B. Revision 4 of that standard (2025) asks for at least 15 characters when a password is the only factor, and at least 8 when it is used with another. Requesters may sign in without a second factor, so their 12-character passwords fell short, although the README said the toolkit follows the standard.

Revision 4 also forbids composition rules. Yet some audits, contracts and PCI DSS 8.3.6 ask for them.

## Decision

The owner chose both changes (2026-11-04).

- **Length:** `PasswordPolicy` asks for 15 characters when the account neither uses a second factor nor must use one, and 12 otherwise. The check runs when a password is chosen: existing passwords stay valid.
- **Composition:** a per-organization setting, off by default, adds upper and lower case letters, a digit and a symbol. A space counts as a symbol (Laravel's rule), so passphrases stay possible.
- **Shared accounts:** the strictest organization decides, because a password belongs to the account.

## Alternatives considered

- **Composition required for everyone:** the toolkit could then no longer say it follows NIST SP 800-63B.
- **15 characters for everyone:** stricter than the standard asks for people with a second factor.
- **Refusing to turn off the second factor when the password is short:** the length cannot be read from a hash, so storing it, or a flag, would be needed.

## Consequences

- An organization that turns composition on departs from NIST SP 800-63B, and the setting's help text says so.
- Someone with a 12-character password who later stops using a second factor keeps it until they next change it, as do requesters with existing 12-to-14-character passwords. Compliance with revision 4 for those accounts comes only when they change their password.
- The reset and invitation page gives the general rule: the person is not identified until the link is used. A password refused there names what is missing.
- Active Directory and single sign-on accounts have no password here, so neither rule touches them.
