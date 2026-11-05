# Privileged access (PAM)

A vault for privileged accounts ([ADR 0078](../adr/0078-privileged-access-vault.md)). Code: `modules/Pam/`. Data: `pam_accounts`, `pam_account_grants`.

## What it does

- **Accounts:** name, username, type, owner, description; linked to a configuration item and an application through Core's contracts.
- **Credential:** encrypted by `PamVault` with `PAM_KEY`, hidden from serialization and the audit trail.
- **Grants:** who may ask for a credential. Only people with `pam.vault.use`, and nobody grants themselves.
- **Showing:** `RevealSecret` needs a fresh code (`ConfirmSecondFactor`) from an app set up at least 24 hours ago (`PAM_SECOND_FACTOR_SETTLE_HOURS`), and a reason, and writes `secret_revealed` to the audit trail.
- **Other changes:** giving access, replacing a credential and deleting an account also need a fresh code. Giving access emails the person, the owner and other managers.
- **Access log:** `/privileged-access/log`, from the audit trail.

## Rights

| Permission | Gives | Default roles |
|---|---|---|
| `pam.vault.use` | Ask for credentials granted to you | Agent, Team lead, Asset manager, Application manager, Security officer, Administrator |
| `pam.account.manage` | Keep accounts and grants. **No credential access** | Security officer, Administrator |
| `pam.audit.view` | Read the access log | Security officer, Auditor, Administrator |

An account's owner can also keep that account, if they have `pam.vault.use`.

## Operating it

- `php artisan pam:key` prints a new key for `PAM_KEY`.
- To change the key: set the old one in `PAM_PREVIOUS_KEYS`, the new one in `PAM_KEY`, then run `php artisan pam:rekey`.
- Back up `PAM_KEY` apart from the database.
