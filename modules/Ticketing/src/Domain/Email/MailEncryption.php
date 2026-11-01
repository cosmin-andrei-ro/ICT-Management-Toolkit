<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Domain\Email;

enum MailEncryption: string
{
    case Ssl = 'ssl';
    case StartTls = 'starttls';
    case None = 'none';

    public function label(): string
    {
        return __('ticketing::email.encryption.'.$this->value);
    }
}
