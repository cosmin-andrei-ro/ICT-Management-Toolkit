<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Cmdb\Application\Import;

use Illuminate\Database\ConnectionInterface;
use Modules\Cmdb\Application\Actions\ManageRelationships;
use Modules\Cmdb\Domain\Enums\RelationshipType;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Cmdb\Domain\Models\Relationship;
use Modules\Core\Application\Imports\ImportHasErrors;
use Modules\Core\Application\Imports\ImportRow;

/**
 * Relationships from a spreadsheet: columns source, relationship, target, where
 * source and target are item identifiers and relationship is a type ("runs_on")
 * or its label ("runs on"). Existing relationships are left as they are.
 */
final readonly class RelationshipImport
{
    public const array COLUMNS = ['source', 'relationship', 'target'];

    public function __construct(
        private ConnectionInterface $db,
        private ManageRelationships $relationships,
    ) {}

    /**
     * @param  list<array{line: int, values: array<string, string>}>  $rows
     * @return list<ImportRow>
     */
    public function analyse(array $rows): array
    {
        return array_map(fn (array $planned): ImportRow => $planned['row'], $this->plan($rows));
    }

    /**
     * @param  list<array{line: int, values: array<string, string>}>  $rows
     * @return list<array{row: ImportRow, source: ?ConfigurationItem, target: ?ConfigurationItem, type: ?RelationshipType}>
     */
    private function plan(array $rows): array
    {
        $identifiers = [];

        foreach ($rows as $row) {
            $identifiers[] = mb_strtolower(trim($row['values']['source'] ?? ''));
            $identifiers[] = mb_strtolower(trim($row['values']['target'] ?? ''));
        }

        $items = ConfigurationItem::query()->whereIn($this->db->raw('lower(identifier)'), array_values(array_unique(array_filter($identifiers))) ?: [''])->get()
            ->keyBy(fn (ConfigurationItem $item): string => mb_strtolower((string) $item->identifier));
        $result = [];

        foreach ($rows as $row) {
            $values = $row['values'];
            $errors = [];
            $source = $items->get(mb_strtolower(trim($values['source'] ?? '')));
            $target = $items->get(mb_strtolower(trim($values['target'] ?? '')));
            $type = $this->type($values['relationship'] ?? '');

            if ($source === null) {
                $errors[] = ImportRow::message('cmdb::import.errors.source', ['value' => $values['source'] ?? '']);
            }

            if ($target === null) {
                $errors[] = ImportRow::message('cmdb::import.errors.target', ['value' => $values['target'] ?? '']);
            }

            if ($type === null) {
                $errors[] = ImportRow::message('cmdb::import.errors.relationship', ['value' => $values['relationship'] ?? '']);
            }

            if ($source !== null && $target !== null && $source->is($target)) {
                $errors[] = ImportRow::message('cmdb::import.errors.self');
            }

            $exists = $source !== null && $target !== null && $type !== null
                && Relationship::query()->where('source_id', $source->id)->where('target_id', $target->id)->where('type', $type)->exists();

            $label = trim(($values['source'] ?? '').' '.($type?->label() ?? ($values['relationship'] ?? '')).' '.($values['target'] ?? ''));

            $result[] = [
                'row' => new ImportRow($row['line'], $label, match (true) {
                    $errors !== [] => ImportRow::ERROR,
                    $exists => ImportRow::UNCHANGED,
                    default => ImportRow::CREATE,
                }, $errors),
                'source' => $source,
                'target' => $target,
                'type' => $type,
            ];
        }

        return $result;
    }

    /**
     * @param  list<array{line: int, values: array<string, string>}>  $rows
     *
     * @throws ImportHasErrors when any row is invalid: nothing is saved
     */
    public function apply(array $rows): int
    {
        $plan = $this->plan($rows);

        if (array_filter($plan, fn (array $planned): bool => $planned['row']->action === ImportRow::ERROR) !== []) {
            throw new ImportHasErrors;
        }

        return $this->db->transaction(function () use ($plan): int {
            $created = 0;

            foreach ($plan as $planned) {
                if ($planned['row']->action === ImportRow::CREATE && $planned['source'] !== null && $planned['target'] !== null && $planned['type'] !== null) {
                    $this->relationships->add($planned['source'], $planned['type'], $planned['target']);
                    $created++;
                }
            }

            return $created;
        });
    }

    private function type(string $value): ?RelationshipType
    {
        $value = mb_strtolower(trim($value));

        foreach (RelationshipType::cases() as $type) {
            if ($value !== '' && in_array($value, [$type->value, mb_strtolower($type->label()), str_replace('_', ' ', $type->value)], true)) {
                return $type;
            }
        }

        return null;
    }
}
