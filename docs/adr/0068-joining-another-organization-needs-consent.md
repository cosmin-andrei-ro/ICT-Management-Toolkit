# ADR 0068 — Joining another organization needs the person's consent

## Status

Accepted (2026-11-03)

## Context

Accounts are global and belong to organizations (`core_organization_user`). The security review of 2026-11-03 found that an administrator of organization A could:

- "invite" the email of someone who had an account in organization B, and the account was added to A silently, without asking the person;
- then reset that account's second factor, lift its lock and rename it. These changes apply to the account everywhere, so they also weaken B.

The answer to the invitation also differed ("invitation sent" or "added existing"), so anyone allowed to invite could find out whether an email had an account anywhere in the instance.

Single sign-on made this worse. An identity provider of B with a broad allowed domain could create the account that A later invited. B could then reach A by signing in again through its provider, because an existing identity was not re-checked.

## Decision

The owner chose explicit acceptance (2026-11-03).

- **Invitation instead of membership:** inviting an email that already has an account creates a pending invitation (`core_organization_invitations`: organization, person, roles, inviter, expiry after 14 days) and emails the person. The administrator sees "Invitation sent." whether or not the account exists. A new email still gets a new local account and a password link (ADR 0017).
- **Consent:** the person, signed in, joins or declines at `/account/invitations`. Every action looks the invitation up among their own pending ones.
- **Single sign-on boundary:**
  - An account with an identity at another organization's provider cannot accept.
  - At every SSO sign-in, not only at linking, an account that belongs to any organization other than the provider's is refused (`other_organizations`).
  - A provider's "trust the second factor" applies only to accounts the provider created. A local or AD account linked by email keeps its own second factor, so whoever configures a provider cannot sign in as an existing administrator without that administrator's code.
- **Shared accounts:** renaming, resetting the second factor and unlocking need the new `manageAccount` policy, which requires the account to belong to the current organization alone. Deactivation and anonymization already required this.
- **API tokens:** Administration → API tokens lists tokens bound to the organization. Of older tokens bound to no organization, it lists only those of members who belong to this organization alone.

## Alternatives considered

- **Only restrict account-wide actions, keep silent adding:** less work. Rejected by the owner, because the account would still appear in A without the person's knowledge.
- **Unique, verified SSO domains across organizations:** heavier, because it needs domain verification. The organization boundary on every SSO sign-in closes the same chain.

## Consequences

- Shared accounts cannot be unlocked or have their second factor reset by any organization's administrator. The person resets their password, or uses a recovery code. An operator can still act in the database if needed.
- Administrators do not see pending invitations in People yet. Inviting again renews an invitation.
- An SSO account that needs a second organization needs a separate account.
