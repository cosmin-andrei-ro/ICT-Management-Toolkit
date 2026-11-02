<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Domain\Enums;

/**
 * What a control test found. Mirrored by audit_control_tests_result_check.
 */
enum TestResult: string
{
    case Effective = 'effective';
    case PartiallyEffective = 'partially_effective';
    case Ineffective = 'ineffective';

    public function label(): string
    {
        return __('audit::evidence.results.'.$this->value);
    }
}
