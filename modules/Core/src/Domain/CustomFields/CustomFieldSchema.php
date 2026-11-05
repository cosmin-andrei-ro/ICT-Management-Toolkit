<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\CustomFields;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Application\Policies\CustomFieldSchemaPolicy;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Core\Domain\Forms\FormSchema;

/**
 * The extra fields an organization wants on one kind of record.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $entity_type
 * @property list<array<string, mixed>> $schema
 */
#[Table('core_custom_field_schemas')]
#[UsePolicy(CustomFieldSchemaPolicy::class)]
#[Fillable(['entity_type', 'schema'])]
final class CustomFieldSchema extends Model
{
    use Auditable;
    use BelongsToOrganization;
    use HasUuids;
    use TracksAuthors;

    protected static function booted(): void
    {
        // An invalid form must never be stored. Returns nothing: "saving" halts on a value.
        self::saving(function (self $schema): void {
            FormSchema::fromArray($schema->schema);
        });
    }

    public function form(): FormSchema
    {
        return FormSchema::fromArray($this->schema);
    }

    protected function casts(): array
    {
        return ['schema' => 'array'];
    }
}
