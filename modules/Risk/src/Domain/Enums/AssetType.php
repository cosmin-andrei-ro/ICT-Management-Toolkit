<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Domain\Enums;

/**
 * What a risk can concern. Mirrored by risk_risk_assets_type_check.
 */
enum AssetType: string
{
    case Application = 'application';
    case ConfigurationItem = 'configuration_item';
    case Process = 'process';
    case Supplier = 'supplier';

    public function label(): string
    {
        return __('risk::risks.asset_types.'.$this->value);
    }
}
