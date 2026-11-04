<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Modules\Core\Domain\Models\Tag;

/**
 * A record that can carry tags, through the HasTags trait.
 */
interface Taggable
{
    /**
     * @return MorphToMany<Tag, covariant \Illuminate\Database\Eloquent\Model>
     */
    public function tags(): MorphToMany;
}
