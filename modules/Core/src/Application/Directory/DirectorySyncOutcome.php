<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Directory;

enum DirectorySyncOutcome: string
{
    case Active = 'active';
    case Disabled = 'disabled';
    case NotFound = 'not_found';
    case EmailConflict = 'email_conflict';
}
