<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Audit;

use Illuminate\Support\Str;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Models\User;

/**
 * Audit entries as people read them: one row per field with its old and new
 * value. Column names become labels ("owner_id" → "Owner"), and the ids of
 * people are shown as their names; other references stay as short ids,
 * since the log keeps what was stored, not what it pointed to.
 */
final class AuditChanges
{
    private const int MAX_LENGTH = 300;

    private const array HIDDEN = ['id', 'organization_id'];

    /** @var array<string, string> */
    private array $names = [];

    /**
     * Loads the names of the people the entries mention, in one query.
     *
     * @param  iterable<AuditLog>  $entries
     */
    public function load(iterable $entries): self
    {
        $ids = [];

        foreach ($entries as $entry) {
            foreach ([$entry->old_values ?? [], $entry->new_values ?? []] as $values) {
                array_walk_recursive($values, function (mixed $value) use (&$ids): void {
                    if (is_string($value) && Str::isUuid($value)) {
                        $ids[$value] = true;
                    }
                });
            }
        }

        $this->names = $ids === [] ? [] : User::query()->whereKey(array_keys($ids))->get(['id', 'name'])->mapWithKeys(fn (User $user): array => [$user->id => $user->name])->all();

        return $this;
    }

    /**
     * @return list<array{field: string, old: ?string, new: ?string}>
     */
    public function rows(AuditLog $entry): array
    {
        $old = $entry->old_values ?? [];
        $new = $entry->new_values ?? [];
        $rows = [];

        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $field) {
            // The record's own id and organization say nothing the entry does not already.
            if (in_array($field, self::HIDDEN, true)) {
                continue;
            }

            $rows[] = [
                'field' => self::label((string) $field),
                'old' => array_key_exists($field, $old) ? $this->value($old[$field]) : null,
                'new' => array_key_exists($field, $new) ? $this->value($new[$field]) : null,
            ];
        }

        return $rows;
    }

    /**
     * @param  iterable<AuditLog>  $entries
     */
    public static function for(iterable $entries): self
    {
        return (new self)->load($entries);
    }

    private static function label(string $field): string
    {
        return Str::headline(preg_replace('/_(id|at|on)$/', '', $field) ?? $field);
    }

    private function value(mixed $value): string
    {
        $text = match (true) {
            $value === null => '—',
            is_bool($value) => $value ? __('core::audit.yes') : __('core::audit.no'),
            is_string($value) && Str::isUuid($value) => $this->names[$value] ?? substr($value, 0, 8).'…',
            is_scalar($value) => (string) $value,
            default => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
        };

        return Str::limit($text, self::MAX_LENGTH);
    }
}
