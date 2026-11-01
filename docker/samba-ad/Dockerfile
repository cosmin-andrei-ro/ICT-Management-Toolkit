# SPDX-License-Identifier: AGPL-3.0-or-later
# Copyright (C) 2026 ICT Management Toolkit contributors
#
# Disposable Active Directory domain controller (Samba) for local development and
# integration tests only. Never use it, or its demo passwords, in production.

FROM debian:trixie-slim

RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        samba samba-ad-dc samba-ad-provision krb5-user winbind ldb-tools ldap-utils ca-certificates \
    && rm -rf /var/lib/apt/lists/* \
    && rm -f /etc/samba/smb.conf

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 389 636

# Same check the application makes: LDAPS by hostname, certificate verified against the domain CA.
HEALTHCHECK --interval=10s --timeout=5s --retries=30 \
    CMD LDAPTLS_CACERT=/var/lib/samba/private/tls/ca.pem \
        ldapsearch -LLL -H "ldaps://$(hostname -f)" -x -b '' -s base defaultNamingContext >/dev/null 2>&1 || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
