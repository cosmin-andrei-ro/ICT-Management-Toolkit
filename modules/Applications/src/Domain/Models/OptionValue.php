<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Applications\Application\Policies\SettingsPolicy;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;

/**
 * One value of a dropdown of the sheet. Sheets keep the key, so renaming the
 * label renames it everywhere; a value in use is deactivated, not deleted.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $list
 * @property string $key
 * @property string $label
 * @property int $position
 * @property bool $is_active
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
#[Table('applications_option_values')]
#[UsePolicy(SettingsPolicy::class)]
#[Fillable(['list', 'key', 'label', 'position', 'is_active'])]
final class OptionValue extends Model
{
    use Auditable;
    use BelongsToOrganization;
    use HasUuids;
    use TracksAuthors;

    /** @var array<string, mixed> */
    protected $attributes = [
        'position' => 0,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
