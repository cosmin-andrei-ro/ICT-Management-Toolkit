<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Domain\Enums;

/**
 * ISO/IEC 27005 risk treatment options. Mirrored by risk_risks_treatment_check.
 */
enum Treatment: string
{
    case Mitigate = 'mitigate';
    case Transfer = 'transfer';
    case Avoid = 'avoid';
    case Accept = 'accept';

    public function label(): string
    {
        return __('risk::treatment.options.'.$this->value);
    }
}
