<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A reminder already sent, so the daily job sends each one once. Written by
 * SendContractReminders only.
 *
 * @property string $id
 * @property string $contract_id
 * @property CarbonImmutable $deadline
 * @property int $days_before
 * @property CarbonImmutable $sent_at
 */
#[Table('core_contract_reminders')]
final class ContractReminder extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'deadline' => 'immutable_date',
            'days_before' => 'integer',
            'sent_at' => 'immutable_datetime',
        ];
    }
}
