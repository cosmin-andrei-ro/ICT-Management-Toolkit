# Active Directory for development

An optional Samba domain controller (`docker/samba-ad`) gives you a real Active Directory to develop and test AD sign-in against, without a Windows server. It is for development only: its passwords are public.

## Start it

```bash
COMPOSE_PROFILES=ad ./vendor/bin/sail up -d
```

The first start provisions the domain, which takes about a minute. The container is ready when `docker compose ps` shows it as `healthy`. Data lives in the `samba-ad-data` volume; delete the volume to start over with a fresh domain.

## Point the application at it

Add to your `.env`:

```dotenv
LDAP_ENABLED=true
LDAP_HOSTS=dc1.corp.example
LDAP_PORT=636
LDAP_BASE_DN=dc=corp,dc=example
LDAP_USERNAME=svc-ictmt@corp.example
LDAP_PASSWORD=Service-Dev-Passw0rd!
LDAP_ENCRYPTION=ldaps
LDAP_CA_CERT=/etc/ssl/samba-ad/ca.pem
```

The domain's CA is shared with the application container through the `samba-ad-tls` volume, so certificate verification stays on, as in production.

Check the connection and a user:

```bash
./vendor/bin/sail artisan core:directory:test
./vendor/bin/sail artisan core:directory:test jdoe
```

## Test domain

Domain `CORP.EXAMPLE` (NetBIOS `CORP`), base DN `DC=corp,DC=example`.

| Account | Password | Groups | Purpose |
|---|---|---|---|
| `svc-ictmt` | `Service-Dev-Passw0rd!` | — | Service account for lookups |
| `jdoe` (Jane Doe) | `User-Dev-Passw0rd!` | IT-ServiceDesk | Regular member |
| `asmith` (Alex Smith) | `User-Dev-Passw0rd!` | IT-Admins | Different group |
| `bnested` (Bo Nested) | `User-Dev-Passw0rd!` | IT-L2 → IT-ServiceDesk | Nested membership |
| `cdisabled` (Cy Disabled) | `User-Dev-Passw0rd!` | IT-ServiceDesk | Disabled in AD |
| `dnogroup` (Dee NoGroup) | `User-Dev-Passw0rd!` | — | No mapped group |
| `Administrator` | `Admin-Dev-Passw0rd!` | Domain Admins | Domain administration |

Groups are in `OU=Groups,DC=corp,DC=example`, for example `CN=IT-ServiceDesk,OU=Groups,DC=corp,DC=example`. To sign in to the application with an AD account, first map a group to a role in the admin panel (Access → AD group mappings).

You can override the passwords with `SAMBA_ADMIN_PASSWORD`, `SAMBA_USER_PASSWORD` and `SAMBA_SERVICE_PASSWORD` before the first start.

## Integration tests

```bash
./vendor/bin/sail exec -e AD_INTEGRATION=1 laravel.test php vendor/bin/pest --group=active-directory
```

They skip themselves unless `AD_INTEGRATION` is set, so the regular test run and CI do not need the container.
