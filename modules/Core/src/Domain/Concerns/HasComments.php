<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Core\Domain\Models\Comment;

/**
 * The model's policy must define "comment", "commentInternally" and "viewInternal"
 * (see docs/modules/Core.md, "Comments and attachments").
 *
 * @mixin Model
 */
trait HasComments
{
    /**
     * @return MorphMany<Comment, $this>
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')->oldest()->oldest('id');
    }
}
