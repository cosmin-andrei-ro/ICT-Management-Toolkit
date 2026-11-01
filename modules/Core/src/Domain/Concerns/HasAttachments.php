<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Core\Domain\Models\Attachment;

/**
 * The model's policy must define "view", "comment", "commentInternally" and
 * "viewInternal" (see docs/modules/Core.md, "Comments and attachments").
 *
 * @mixin Model
 */
trait HasAttachments
{
    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->oldest()->oldest('id');
    }
}
