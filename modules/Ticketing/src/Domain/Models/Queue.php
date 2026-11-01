<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Core\Domain\Models\Team;
use Modules\Ticketing\Application\Policies\TicketingSettingsPolicy;
use Modules\Ticketing\Database\Factories\QueueFactory;

/**
 * Where tickets wait for a team. Tickets created without a queue go to the
 * organization's default queue.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $team_id
 * @property string $name
 * @property ?string $description
 * @property bool $is_default
 * @property bool $is_active
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 * @property-read Team $team
 */
#[Table('ticketing_queues')]
#[UseFactory(QueueFactory::class)]
#[UsePolicy(TicketingSettingsPolicy::class)]
#[Fillable(['team_id', 'name', 'description', 'is_default', 'is_active'])]
final class Queue extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<QueueFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;
    use TracksAuthors;

    protected static function booted(): void
    {
        // One default queue per organization (unique index): taking the flag moves it.
        self::saving(function (self $queue): void {
            if ($queue->is_default && $queue->isDirty('is_default')) {
                self::query()->where('is_default', true)->when($queue->exists, fn ($query) => $query->whereKeyNot($queue->id))
                    ->each(fn (self $previous) => $previous->forceFill(['is_default' => false])->saveQuietly());
            }
        });

        self::creating(function (self $queue): void {
            if (! Team::query()->whereKey($queue->team_id)->exists()) {
                throw new InvalidArgumentException('The queue\'s team must belong to the current organization.');
            }
        });
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
