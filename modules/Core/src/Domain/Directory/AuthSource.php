<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Directory;

/**
 * Where an account's password is verified. Fixed per account: there is no fallback
 * from one source to the other. Mirrored by the users_auth_source_check constraint.
 */
enum AuthSource: string
{
    case Local = 'local';
    case Ldap = 'ldap';
    // Created by single sign-on (OIDC): signs in only through its identity provider.
    case Sso = 'sso';
}
