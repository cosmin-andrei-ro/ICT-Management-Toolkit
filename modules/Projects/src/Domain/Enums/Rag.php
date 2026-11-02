<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Domain\Enums;

/**
 * Red, amber or green: how a project, or one side of it, is doing.
 */
enum Rag: string
{
    case Green = 'green';
    case Amber = 'amber';
    case Red = 'red';

    public function label(): string
    {
        return __('projects::governance.rag.'.$this->value);
    }
}
