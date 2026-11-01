<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Core\Application\Policies\BusinessCalendarPolicy;
use Modules\Core\Database\Factories\BusinessCalendarFactory;
use Modules\Core\Domain\Calendar\WorkingSchedule;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property string $timezone
 * @property array<int|string, list<array{0: string, 1: string}>> $weekly_hours
 * @property ?string $public_holidays
 * @property bool $is_default
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 */
#[Table('core_business_calendars')]
#[UseFactory(BusinessCalendarFactory::class)]
#[UsePolicy(BusinessCalendarPolicy::class)]
#[Fillable(['name', 'timezone', 'weekly_hours', 'public_holidays', 'is_default'])]
final class BusinessCalendar extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<BusinessCalendarFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;
    use TracksAuthors;

    protected static function booted(): void
    {
        // Validate through the value object: an invalid calendar must never be stored.
        // Returns nothing on purpose: "saving" listeners halt at the first non-null result.
        self::saving(function (self $calendar): void {
            $calendar->toSchedule();
        });

        // One default calendar per organization (unique index): taking the flag moves it.
        self::saving(function (self $calendar): void {
            if ($calendar->is_default && $calendar->isDirty('is_default')) {
                self::query()->where('is_default', true)->when($calendar->exists, fn ($query) => $query->whereKeyNot($calendar->id))
                    ->each(fn (self $previous) => $previous->forceFill(['is_default' => false])->saveQuietly());
            }
        });
    }

    /**
     * @return HasMany<CalendarHoliday, $this>
     */
    public function holidays(): HasMany
    {
        return $this->hasMany(CalendarHoliday::class, 'calendar_id')->orderBy('date');
    }

    public function toSchedule(): WorkingSchedule
    {
        $weekly = [];
        foreach ($this->weekly_hours as $weekday => $intervals) {
            $weekly[(int) $weekday] = $intervals;
        }

        $extra = $this->exists
            ? array_values($this->holidays->map(fn (CalendarHoliday $holiday): string => $holiday->date->format('Y-m-d'))->all())
            : [];

        return new WorkingSchedule($this->timezone, $weekly, $extra, $this->public_holidays);
    }

    protected function casts(): array
    {
        return [
            'weekly_hours' => 'array',
            'is_default' => 'boolean',
        ];
    }
}
