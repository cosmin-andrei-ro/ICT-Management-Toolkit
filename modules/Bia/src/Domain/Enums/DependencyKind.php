<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Domain\Enums;

enum DependencyKind: string
{
    case Supplier = 'supplier';
    case Location = 'location';
    case Process = 'process';
    case Role = 'role';
    case Person = 'person';

    public function label(): string
    {
        return __('bia::dependencies.kinds.'.$this->value);
    }
}
