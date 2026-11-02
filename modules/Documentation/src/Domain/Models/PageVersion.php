<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Documentation\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Models\User;

/**
 * A saved state of a page, kept as it was: the history of its text.
 *
 * @property string $id
 * @property string $page_id
 * @property int $version
 * @property string $title
 * @property string $body
 * @property ?string $summary
 * @property ?string $author_id
 * @property ?string $approved_by
 * @property ?Carbon $approved_at
 * @property Carbon $created_at
 * @property-read ?User $author
 * @property-read ?User $approver
 */
#[Table('documentation_page_versions')]
final class PageVersion extends Model
{
    use HasUuids;

    public const ?string UPDATED_AT = null;

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    protected function casts(): array
    {
        return ['version' => 'integer', 'approved_at' => 'datetime'];
    }
}
