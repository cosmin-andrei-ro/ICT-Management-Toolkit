# Email-to-ticket for development

An optional GreenMail server gives you a real SMTP and IMAP server to develop and test email intake against. It is for development only: it keeps mail in memory, and its password is public.

## Start it

```bash
COMPOSE_PROFILES=mail ./vendor/bin/sail up -d
```

It has one mailbox, `helpdesk@corp.example` (password `helpdesk-dev-only`), and accepts mail for any address on SMTP port 3025. IMAP is on port 3143, without TLS. Restarting the container empties it.

## Point the application at it

GreenMail's ports and the missing TLS are refused by default. Allow them in your `.env` (never in production):

```dotenv
TICKETING_MAIL_ALLOWED_PORTS=993,143,3143
TICKETING_MAIL_ALLOW_UNENCRYPTED=true
```

Then, in Administration → Service desk → **Mailboxes**, add a mailbox:

| Field | Value |
|---|---|
| Email address | `helpdesk@corp.example` |
| IMAP server | `greenmail` |
| Port | `3143` |
| Encryption | None (development only) |
| Username | `helpdesk@corp.example` |
| Password | `helpdesk-dev-only` |

Use **Test connection** to check it.

## Send a message and read it

The sender must be a user of the organization. Send a message from their address, for example from tinker:

```bash
./vendor/bin/sail artisan tinker --execute 'config(["mail.mailers.smtp.host" => "greenmail", "mail.mailers.smtp.port" => 3025]); Mail::mailer("smtp")->raw("The printer is offline.", fn ($m) => $m->from("jane@example.com")->to("helpdesk@corp.example")->subject("Printer"));'
./vendor/bin/sail artisan ticketing:mail:fetch
./vendor/bin/sail artisan queue:work --once
```

The scheduler runs `ticketing:mail:fetch` every minute; the reading itself happens in the queue. **Incoming email** in the administration lists what happened to each message.

## Integration tests

```bash
./vendor/bin/sail exec -e MAIL_INTEGRATION=1 laravel.test php vendor/bin/pest modules/Ticketing/tests/Integration
```

They are skipped unless `MAIL_INTEGRATION` is set.
