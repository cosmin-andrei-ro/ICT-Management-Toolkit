<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Applications\Application\Policies\SheetPolicy;
use Modules\Applications\Database\Factories\SheetFactory;
use Modules\Applications\Domain\Enums\SheetStatus;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Core\Domain\Models\User;

/**
 * One version of an application's documentation sheet. Its content follows
 * SheetSchema, by section key. Editable while draft or returned; versions in
 * force or superseded never change. Written through the sheet actions only.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $application_id
 * @property SheetStatus $status
 * @property bool $is_major
 * @property ?int $major
 * @property ?int $minor
 * @property ?string $change_summary
 * @property array<string, mixed> $content
 * @property ?Carbon $submitted_at
 * @property ?string $submitted_by
 * @property ?Carbon $approved_at
 * @property ?Carbon $returned_at
 * @property ?string $returned_by
 * @property ?string $return_comment
 * @property ?string $created_by
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read Application $application
 * @property-read ?User $submitter
 * @property-read ?User $returner
 * @property-read ?User $author
 */
#[Table('applications_sheets')]
#[UseFactory(SheetFactory::class)]
#[UsePolicy(SheetPolicy::class)]
final class Sheet extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<SheetFactory> */
    use HasFactory;

    use HasUuids;
    use TracksAuthors;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'draft',
        'content' => '{}',
        'is_major' => false,
    ];

    /**
     * "1.2" once in force; null before.
     */
    public function versionLabel(): ?string
    {
        return $this->major !== null && $this->minor !== null ? $this->major.'.'.$this->minor : null;
    }

    /**
     * The values of one section.
     *
     * @return array<mixed>
     */
    public function section(string $key): array
    {
        $values = $this->content[$key] ?? [];

        return is_array($values) ? $values : [];
    }

    /**
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class)->withTrashed();
    }

    /**
     * @return HasMany<SheetApproval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(SheetApproval::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function returner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'status' => SheetStatus::class,
            'is_major' => 'boolean',
            'major' => 'integer',
            'minor' => 'integer',
            'content' => 'array',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }
}
