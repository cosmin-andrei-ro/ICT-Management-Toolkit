<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Domain\Models\User;

/**
 * Fills created_by / updated_by from the authenticated user. Left untouched when
 * no user is authenticated (system actions), so seeders can set them explicitly.
 *
 * @mixin Model
 */
trait TracksAuthors
{
    public static function bootTracksAuthors(): void
    {
        static::creating(function (Model $model): void {
            $userId = Auth::id();

            if ($userId === null) {
                return;
            }

            $model->setAttribute('created_by', $model->getAttribute('created_by') ?? $userId);
            $model->setAttribute('updated_by', $userId);
        });

        static::updating(function (Model $model): void {
            $userId = Auth::id();

            if ($userId !== null) {
                $model->setAttribute('updated_by', $userId);
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
