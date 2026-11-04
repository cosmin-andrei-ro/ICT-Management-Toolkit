<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Modules\Core\Domain\Models\Tag;

/**
 * For records that can carry tags (ADR 0072).
 *
 * @mixin Model
 */
trait HasTags
{
    public static function bootHasTags(): void
    {
        // Soft-deleted records keep their tags, for when they are restored.
        static::deleted(function (Model $record): void {
            if ($record instanceof Taggable && (! method_exists($record, 'isForceDeleting') || $record->isForceDeleting())) {
                $record->tags()->detach();
            }
        });
    }

    /**
     * @return MorphToMany<Tag, $this>
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable', 'core_taggables');
    }
}
