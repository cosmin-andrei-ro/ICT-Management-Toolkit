<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Application\Policies\TagPolicy;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;

/**
 * A free label on records of any module (ADR 0072), one list per organization.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
#[Table('core_tags')]
#[UsePolicy(TagPolicy::class)]
#[Fillable(['name'])]
final class Tag extends Model
{
    use Auditable;
    use BelongsToOrganization;
    use HasUuids;
    use TracksAuthors;

    public const int MAX_LENGTH = 50;

    /**
     * One spelling per tag: "Critical  Vendor" and "critical vendor" are the same.
     */
    public static function normalize(string $name): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $name)));
    }

    public static function isValidName(string $name): bool
    {
        return mb_strlen($name) <= self::MAX_LENGTH && preg_match('/^[\p{L}\p{N}][\p{L}\p{N} ._\-]*$/u', $name) === 1;
    }
}
