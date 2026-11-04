#!/bin/sh
# SPDX-License-Identifier: AGPL-3.0-or-later
# Copyright (C) 2026 ICT Management Toolkit contributors
#
# Provisions the CORP.EXAMPLE test domain on first start, seeds test accounts,
# publishes the TLS CA for the application container, then runs Samba.
set -eu

REALM="${SAMBA_REALM:-CORP.EXAMPLE}"
DOMAIN="${SAMBA_DOMAIN:-CORP}"
ADMIN_PASSWORD="${SAMBA_ADMIN_PASSWORD:-Admin-Dev-Passw0rd!}"
USER_PASSWORD="${SAMBA_USER_PASSWORD:-User-Dev-Passw0rd!}"
SERVICE_PASSWORD="${SAMBA_SERVICE_PASSWORD:-Service-Dev-Passw0rd!}"
BASE_DN="DC=$(echo "$REALM" | tr '[:upper:]' '[:lower:]' | sed 's/\./,DC=/g')"
CA_EXPORT_DIR="/export/tls"

if [ ! -f /var/lib/samba/private/sam.ldb ]; then
    echo "Provisioning domain ${REALM}..."
    # xattr_tdb keeps NT ACLs in a database instead of filesystem security xattrs,
    # which an unprivileged container may not write.
    samba-tool domain provision \
        --realm="$REALM" \
        --domain="$DOMAIN" \
        --server-role=dc \
        --dns-backend=SAMBA_INTERNAL \
        --adminpass="$ADMIN_PASSWORD" \
        --option="dns forwarder = 127.0.0.11" \
        --option="tls enabled = yes" \
        --option="vfs objects = dfs_samba4 acl_xattr xattr_tdb"

    # Start temporarily to seed accounts through the normal tooling.
    samba -D
    for _ in $(seq 1 60); do
        samba-tool user list >/dev/null 2>&1 && break
        sleep 1
    done

    samba-tool ou create "OU=Staff,${BASE_DN}"
    samba-tool ou create "OU=Groups,${BASE_DN}"
    samba-tool ou create "OU=Service,${BASE_DN}"

    # Read-only service account used by the application for lookups.
    samba-tool user create svc-ictmt "$SERVICE_PASSWORD" --userou=OU=Service \
        --given-name=Service --surname=ICTMT

    samba-tool user create jdoe "$USER_PASSWORD" --userou=OU=Staff \
        --given-name=Jane --surname=Doe --mail-address=jane.doe@corp.example
    samba-tool user create asmith "$USER_PASSWORD" --userou=OU=Staff \
        --given-name=Alex --surname=Smith --mail-address=alex.smith@corp.example
    samba-tool user create bnested "$USER_PASSWORD" --userou=OU=Staff \
        --given-name=Bo --surname=Nested --mail-address=bo.nested@corp.example
    samba-tool user create cdisabled "$USER_PASSWORD" --userou=OU=Staff \
        --given-name=Cy --surname=Disabled --mail-address=cy.disabled@corp.example
    samba-tool user disable cdisabled
    samba-tool user create dnogroup "$USER_PASSWORD" --userou=OU=Staff \
        --given-name=Dee --surname=NoGroup --mail-address=dee.nogroup@corp.example

    samba-tool group add IT-Support --groupou=OU=Groups
    samba-tool group add IT-Admins --groupou=OU=Groups
    samba-tool group add IT-L2 --groupou=OU=Groups

    samba-tool group addmembers IT-Support jdoe,cdisabled
    samba-tool group addmembers IT-Admins asmith
    # Nested: bnested is only in IT-L2, which is a member of IT-Support.
    samba-tool group addmembers IT-L2 bnested
    samba-tool group addmembers IT-Support IT-L2

    samba-tool user setexpiry --noexpiry svc-ictmt
    for u in jdoe asmith bnested cdisabled dnogroup; do samba-tool user setexpiry --noexpiry "$u"; done

    pkill samba || true
    sleep 2

    # Keep the generated configuration on the volume: /etc is rebuilt with the container.
    mv /etc/samba/smb.conf /var/lib/samba/smb.conf
    echo "Provisioning done."
fi

ln -sf /var/lib/samba/smb.conf /etc/samba/smb.conf

# The application container verifies the LDAPS certificate against this CA.
mkdir -p "$CA_EXPORT_DIR"
cp /var/lib/samba/private/tls/ca.pem "$CA_EXPORT_DIR/ca.pem"
chmod 0644 "$CA_EXPORT_DIR/ca.pem"

exec samba --foreground --no-process-group --debug-stdout
