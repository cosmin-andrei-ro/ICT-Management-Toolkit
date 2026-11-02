<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Documentation\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\HasAttachments;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Text\Markdown;
use Modules\Documentation\Application\Policies\PagePolicy;
use Modules\Documentation\Database\Factories\PageFactory;

/**
 * A page of a space, written in Markdown, under another page or at the top.
 * Editors work on the latest version (title, body); readers see the published
 * one (published_title, published_body), which in controlled spaces a space
 * owner approved. Every version is kept in documentation_page_versions; the
 * audit trail records the rest without the text itself.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $space_id
 * @property ?string $parent_id
 * @property string $title
 * @property string $body
 * @property int $version
 * @property int $position
 * @property ?string $published_title
 * @property ?string $published_body
 * @property ?int $published_version
 * @property ?Carbon $published_at
 * @property ?int $pending_version
 * @property ?string $submitted_by
 * @property ?Carbon $submitted_at
 * @property ?string $rejection_note
 * @property ?CarbonImmutable $review_on
 * @property ?string $updated_by
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 * @property-read Space $space
 * @property-read ?Page $parent
 * @property-read ?User $editor
 */
#[Table('documentation_pages')]
#[UseFactory(PageFactory::class)]
#[UsePolicy(PagePolicy::class)]
final class Page extends Model
{
    use Auditable;
    use BelongsToOrganization;
    use HasAttachments;

    /** @use HasFactory<PageFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;
    use TracksAuthors;

    /** @var list<string> */
    protected $hidden = ['search_vector'];

    public function isPublished(): bool
    {
        return $this->published_version !== null;
    }

    /**
     * Whether the latest version is newer than what readers see.
     */
    public function hasDraft(): bool
    {
        return $this->published_version === null || $this->version > $this->published_version;
    }

    public function isReviewOverdue(CarbonImmutable $today): bool
    {
        return $this->review_on !== null && $this->review_on->lt($today);
    }

    /**
     * What readers see.
     */
    public function publishedHtml(): string
    {
        return Markdown::toSafeHtml($this->published_body ?? '');
    }

    /**
     * The latest version, for its editors.
     */
    public function draftHtml(): string
    {
        return Markdown::toSafeHtml($this->body);
    }

    public function displayTitle(): string
    {
        return $this->published_title ?? $this->title;
    }

    /**
     * The versions keep the text: the audit trail records who changed what else.
     *
     * @return list<string>
     */
    public function auditExclude(): array
    {
        return ['body', 'version', 'published_body', 'search_vector'];
    }

    /**
     * @return BelongsTo<Space, $this>
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Page, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position')->orderBy('title');
    }

    /**
     * @return HasMany<PageVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(PageVersion::class)->orderByDesc('version');
    }

    /**
     * @return HasMany<PageLink, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(PageLink::class)->orderBy('title');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'position' => 'integer',
            'published_version' => 'integer',
            'published_at' => 'datetime',
            'pending_version' => 'integer',
            'submitted_at' => 'datetime',
            'review_on' => 'immutable_date',
        ];
    }
}
