<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A day without work on top of the calendar's public holidays (company day off,
 * local holiday). Belongs to its calendar's organization.
 *
 * @property string $id
 * @property string $calendar_id
 * @property Carbon $date
 * @property string $name
 */
#[Table('core_calendar_holidays')]
#[Fillable(['date', 'name'])]
final class CalendarHoliday extends Model
{
    use HasUuids;

    /**
     * @return BelongsTo<BusinessCalendar, $this>
     */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(BusinessCalendar::class, 'calendar_id');
    }

    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
