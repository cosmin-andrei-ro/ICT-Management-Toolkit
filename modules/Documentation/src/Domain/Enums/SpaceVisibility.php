<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Documentation\Domain\Enums;

/**
 * Who reads a space. Mirrored by documentation_spaces_visibility_check.
 */
enum SpaceVisibility: string
{
    // People holding any role beyond Requester.
    case Staff = 'staff';
    case Everyone = 'everyone';
    case Teams = 'teams';

    public function label(): string
    {
        return __('documentation::docs.visibilities.'.$this->value);
    }
}
