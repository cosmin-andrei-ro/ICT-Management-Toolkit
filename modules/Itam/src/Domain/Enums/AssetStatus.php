<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Domain\Enums;

/**
 * Life cycle of an asset, from order to disposal. The allowed moves are listed
 * here and enforced by the actions; disposal is final.
 */
enum AssetStatus: string
{
    case Ordered = 'ordered';
    case InStock = 'in_stock';
    case Deployed = 'deployed';
    case InRepair = 'in_repair';
    case Retired = 'retired';
    case Lost = 'lost';
    case Disposed = 'disposed';

    public function label(): string
    {
        return __('itam::assets.statuses.'.$this->value);
    }

    /**
     * @return list<self>
     */
    public function next(): array
    {
        return match ($this) {
            self::Ordered => [self::InStock, self::Deployed],
            self::InStock => [self::Deployed, self::InRepair, self::Retired, self::Lost],
            self::Deployed => [self::InStock, self::InRepair, self::Lost],
            self::InRepair => [self::InStock, self::Deployed, self::Retired],
            self::Retired => [self::InStock, self::Disposed],
            self::Lost => [self::InStock, self::Disposed],
            self::Disposed => [],
        };
    }

    public function canBecome(self $status): bool
    {
        return in_array($status, $this->next(), true);
    }
}
