<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Domain\Enums;

/**
 * What a project's money goes on.
 */
enum CostCategory: string
{
    case ExternalLabour = 'external_labour';
    case Licences = 'licences';
    case Hardware = 'hardware';
    case Services = 'services';
    case Other = 'other';

    public function label(): string
    {
        return __('projects::budget.categories.'.$this->value);
    }
}
