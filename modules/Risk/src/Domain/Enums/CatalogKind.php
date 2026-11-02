<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Domain\Enums;

/**
 * Mirrored by risk_catalog_entries_kind_check.
 */
enum CatalogKind: string
{
    case Threat = 'threat';
    case Vulnerability = 'vulnerability';

    public function label(): string
    {
        return __('risk::risks.kinds.'.$this->value);
    }
}
