<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;

/**
 * One row per organization: the impact level from which an interruption is
 * unacceptable (it sets the MTPD), and how often processes are reviewed.
 *
 * @property string $organization_id
 * @property int $unacceptable_level
 * @property int $review_months
 * @property ?string $updated_by
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
#[Table('bia_settings')]
final class BiaSettings extends Model
{
    use Auditable;
    use BelongsToOrganization;

    public const int DEFAULT_LEVEL = 4;

    public const int DEFAULT_REVIEW_MONTHS = 12;

    protected $primaryKey = 'organization_id';

    protected $keyType = 'string';

    public $incrementing = false;

    /** @var array<string, mixed> */
    protected $attributes = [
        'unacceptable_level' => self::DEFAULT_LEVEL,
        'review_months' => self::DEFAULT_REVIEW_MONTHS,
    ];

    /**
     * The current organization's settings, or the defaults when never changed.
     */
    public static function current(): self
    {
        return self::query()->first() ?? new self;
    }

    protected function casts(): array
    {
        return [
            'unacceptable_level' => 'integer',
            'review_months' => 'integer',
        ];
    }
}
