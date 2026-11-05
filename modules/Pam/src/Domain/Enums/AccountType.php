<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Pam\Domain\Enums;

/**
 * Mirrored by pam_accounts_type_check.
 */
enum AccountType: string
{
    case Administrator = 'administrator';
    case Service = 'service';
    case Root = 'root';
    case ApiKey = 'api_key';
    case SshKey = 'ssh_key';
    case Other = 'other';

    public function label(): string
    {
        return __('pam::accounts.types.'.$this->value);
    }
}
