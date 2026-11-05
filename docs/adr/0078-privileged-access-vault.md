# ADR 0078 — Privileged access vault

- **Status:** Accepted
- **Date:** 2026-11-05

## Context

The application sheet records only where a credential lives. The owner asked for a privileged access management module and chose, on 2026-11-04, a vault with approved access: accounts linked to CMDB items and applications, credentials shown after a fresh second factor, optional approval and a time limit, everything recorded, rotation reminders and access reviews. No connection to target systems, no automatic rotation, no session recording.

This makes the toolkit a target of much higher value. A vault holds the keys to everything else, so the design assumes attackers will try for it.

## Decision

This covers the vault. Approval, time limits, reminders and reviews follow in the next change.

- **A key of its own (`PAM_KEY`), AES-256-GCM.** A leak of `APP_KEY` or of a database dump alone does not open the vault. A key equal to `APP_KEY` counts as none. Without a key, the module stores and shows nothing, and says so.
- **Plain text exists only in `PamVault`.** The column is hidden from serialization and left out of the audit trail, which records that a credential was shown or changed, never its value. Credentials are not in exports, the API, search or webhooks.
- **Deny by default, and managing is not reading.** `pam.account.manage` and ownership let someone keep an account, but not see its credential. That needs a grant on that account and `pam.vault.use`. Nobody grants themselves. Grants are emailed to the person, the owner and other managers, because this does not stop someone creating a second account for themselves: an administrator can, but not unnoticed.
- **Showing needs a fresh code from an authenticator app and a reason,** through `ConfirmSecondFactor`: rate limited, audited and counted towards the lockout.
- **So do changes a stolen session could profit from:** giving access, replacing a credential and deleting an account also need a code. Removing someone's access does not, so that it stays quick.
- **A new authenticator app must wait 24 hours before it can show credentials,** and setting one up emails the person. Enrolling needs no re-authentication, so a stolen session of someone without an app could otherwise create its own second factor.
- **A shown credential is never in Livewire's page state.** It is view data for one response, gone at the next request, so it is not sent back with later requests or restored from a saved page.
- **A ciphertext is sealed to its account,** so someone able to write to the database cannot move it to an account they may read.
- **Leavers lose access:** leaving an organization deletes their grants and ownership, so being added again later restores nothing.
- **Showings are in the append-only audit trail,** with the reason. The access log reads from there, so there is no second record to keep or to tamper with.
- **Key rotation:** `pam:rekey` moves credentials to the current key, reading through `PAM_PREVIOUS_KEYS`. A credential it cannot read is reported and left alone. The run is recorded.
- **Deleting an account destroys its credential.** The record stays, for the trail.

## Alternatives considered

- **`APP_KEY` with Laravel's `encrypted` cast:** simplest, but one secret then opens sessions and the vault, and `APP_KEY` sits on every web process.
- **A dedicated secrets server or hardware module:** much stronger, since the application would never hold the key, but far heavier to run. It remains the right choice for high-risk environments.
- **Letting managers read everything:** convenient, but a stolen administrator session would then open every credential.

## Consequences

- **`PAM_KEY` must be backed up, apart from the database.** Without it, stored credentials are lost for good. With both, they can be read.
- **`PAM_KEY` still sits in the environment of the application's processes.** An attacker who controls the server can read it. This protects against leaked backups and database access, not against a compromised server.
- **Shown credentials reach the browser,** so a compromised browser can read them. Clipboard history and screenshots are beyond the toolkit's reach.
- **People need an authenticator app:** passkey-only and single sign-on accounts must set one up to read credentials.
- An organization with a single manager cannot give that person access; it needs a second person with the right to manage.
- **The 24-hour wait delays a stolen session; it does not stop one** if the person misses the email. Requiring re-authentication to set up an authenticator app, in Core, would close this properly.
- **The value stays on screen until the poll runs.** The hide is done by the browser, so a modified browser can keep it, and it can be copied.
- **Deactivated people and people who lost their role keep their grants,** which do nothing meanwhile, and work again if they are reactivated.
- An internal review of this module's code found and fixed the points above. It ran no live tests. There has been no independent third-party review: have the module assessed before storing production credentials.
