<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Audit\Domain\Enums\TestResult;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Core\Domain\Models\User;

/**
 * A test of a control: when, by whom, how, and whether it works.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $control_id
 * @property CarbonImmutable $tested_on
 * @property ?string $tester_id
 * @property TestResult $result
 * @property string $method
 * @property ?string $notes
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read ?User $tester
 */
#[Table('audit_control_tests')]
final class ControlTest extends Model
{
    use Auditable;
    use BelongsToOrganization;
    use HasUuids;
    use TracksAuthors;

    /**
     * @return BelongsTo<User, $this>
     */
    public function tester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tester_id');
    }

    /**
     * @return HasMany<Evidence, $this>
     */
    public function evidence(): HasMany
    {
        return $this->hasMany(Evidence::class, 'test_id');
    }

    protected function casts(): array
    {
        return [
            'tested_on' => 'immutable_date',
            'result' => TestResult::class,
        ];
    }
}
