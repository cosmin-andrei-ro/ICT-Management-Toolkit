<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Domain\Enums;

/**
 * What a project delivers; a new application brings its checklist (CLAUDE.md §9).
 */
enum ProjectKind: string
{
    case General = 'general';
    case Application = 'application';
    case Infrastructure = 'infrastructure';

    public function label(): string
    {
        return __('projects::projects.kinds.'.$this->value);
    }
}
