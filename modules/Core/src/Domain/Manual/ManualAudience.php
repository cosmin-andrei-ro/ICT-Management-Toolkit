<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Manual;

enum ManualAudience: string
{
    case Everyone = 'everyone';
    case Agents = 'agents';
    case Administrators = 'administrators';

    public function label(): string
    {
        return __('core::manual.audiences.'.$this->value);
    }
}
