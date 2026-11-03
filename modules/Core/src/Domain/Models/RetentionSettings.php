<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;

/**
 * The organization's retention periods (ADR 0060), by policy: years, or null
 * to keep forever. A policy the organization never set keeps its default.
 *
 * @property string $organization_id
 * @property array<string, int|null> $periods
 * @property ?string $updated_by
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
#[Table('core_retention_settings')]
final class RetentionSettings extends Model
{
    use Auditable;
    use BelongsToOrganization;

    protected $primaryKey = 'organization_id';

    protected $keyType = 'string';

    public $incrementing = false;

    /** @var array<string, mixed> */
    protected $attributes = ['periods' => '{}'];

    public static function current(): self
    {
        return self::query()->first() ?? new self;
    }

    /**
     * @return ?int Years; null keeps forever.
     */
    public function years(string $policy, int $default): ?int
    {
        if (! array_key_exists($policy, $this->periods)) {
            return $default;
        }

        $years = $this->periods[$policy];

        return is_int($years) && $years > 0 ? $years : null;
    }

    protected function casts(): array
    {
        return ['periods' => 'array'];
    }
}
