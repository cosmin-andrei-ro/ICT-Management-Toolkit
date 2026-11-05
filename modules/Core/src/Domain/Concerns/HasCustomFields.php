<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Modules\Core\Domain\CustomFields\CustomFieldValues;

/**
 * For records that can have custom fields (ADR 0077).
 *
 * @mixin Model
 */
trait HasCustomFields
{
    public static function bootHasCustomFields(): void
    {
        // Soft-deleted records keep their values, for when they are restored.
        static::deleted(function (Model $record): void {
            if (! method_exists($record, 'isForceDeleting') || $record->isForceDeleting()) {
                CustomFieldValues::query()->where('entity_type', $record->getMorphClass())->where('entity_id', $record->getKey())->delete();
            }
        });
    }

    /**
     * @return MorphOne<CustomFieldValues, $this>
     */
    public function customFieldValues(): MorphOne
    {
        return $this->morphOne(CustomFieldValues::class, 'entity');
    }
}
