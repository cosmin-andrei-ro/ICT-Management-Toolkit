<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\CustomFields;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Concerns\BelongsToOrganization;

/**
 * The values of a record's custom fields, by field key. Changes are audited on
 * the record itself, so they appear in its history.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $entity_type
 * @property string $entity_id
 * @property array<string, string|int|float|bool|null> $field_values
 * @property ?string $updated_by
 */
#[Table('core_custom_field_values')]
final class CustomFieldValues extends Model
{
    use BelongsToOrganization;
    use HasUuids;

    protected function casts(): array
    {
        return ['field_values' => 'array'];
    }
}
