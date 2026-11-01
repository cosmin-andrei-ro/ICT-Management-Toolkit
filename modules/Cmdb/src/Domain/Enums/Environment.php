<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Cmdb\Domain\Enums;

enum Environment: string
{
    case Production = 'production';
    case Test = 'test';
    case Development = 'development';
    case DisasterRecovery = 'disaster_recovery';

    public function label(): string
    {
        return __('cmdb::items.environments.'.$this->value);
    }
}
