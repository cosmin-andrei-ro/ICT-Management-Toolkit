<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\CustomFields;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\CustomFields\CustomFieldSchema;
use Modules\Core\Domain\CustomFields\CustomFieldValues;
use Modules\Core\Domain\Models\User;

/**
 * Validates a record's custom fields against its kind's schema and stores them.
 * Values of fields since removed from the schema are kept, in case they come
 * back. The caller has checked that the person may change the record.
 */
final readonly class SaveCustomFields
{
    public function __construct(
        private ConnectionInterface $db,
        private ValidationFactory $validation,
        private AuditTrail $trail,
    ) {}

    /**
     * @param  array<string, mixed>  $answers
     *
     * @throws ValidationException keyed by field key
     */
    public function handle(Model $record, array $answers, User $actor): void
    {
        $schema = CustomFieldSchema::query()->where('entity_type', $record->getMorphClass())->first();

        if ($schema === null) {
            return;
        }

        $answered = [];

        foreach ($schema->form()->answer($answers, $this->validation) as $answer) {
            $answered[$answer['key']] = $answer['value'];
        }

        $this->db->transaction(function () use ($record, $answered, $actor): void {
            $stored = CustomFieldValues::query()->where('entity_type', $record->getMorphClass())->where('entity_id', $record->getKey())->lockForUpdate()->first();
            $before = $stored->field_values ?? [];
            $after = [...$before, ...$answered];

            if ($after === $before) {
                return;
            }

            $values = $stored ?? (new CustomFieldValues)->forceFill(['entity_type' => $record->getMorphClass(), 'entity_id' => $record->getKey()]);
            $values->forceFill(['field_values' => $after, 'updated_by' => $actor->id])->save();

            $changedKeys = array_keys(array_filter($after, fn (mixed $value, string $key): bool => ! array_key_exists($key, $before) || $before[$key] !== $value, ARRAY_FILTER_USE_BOTH));
            $this->trail->record(
                AuditEvent::Updated,
                $record,
                ['custom_fields' => array_intersect_key($before, array_flip($changedKeys))],
                ['custom_fields' => array_intersect_key($after, array_flip($changedKeys))],
                $actor->id,
            );
        });
    }
}
