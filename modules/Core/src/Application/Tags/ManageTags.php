<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Tags;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Concerns\Taggable;
use Modules\Core\Domain\Models\Tag;

/**
 * Puts tags on a record and takes them off. The change shows in the record's own
 * history. The caller has checked that the person may change the record.
 */
final readonly class ManageTags
{
    public const int MAX_PER_RECORD = 20;

    public function __construct(
        private ConnectionInterface $db,
        private AuditTrail $trail,
    ) {}

    /**
     * @throws InvalidArgumentException For an invalid name, or when the record has too many tags.
     */
    public function add(Model&Taggable $record, string $name): void
    {
        $name = Tag::normalize($name);

        if (! Tag::isValidName($name)) {
            throw new InvalidArgumentException('invalid');
        }

        $this->db->transaction(function () use ($record, $name): void {
            $before = $this->names($record);

            if (in_array($name, $before, true)) {
                return;
            }

            if (count($before) >= self::MAX_PER_RECORD) {
                throw new InvalidArgumentException('too_many');
            }

            $record->tags()->attach(Tag::query()->firstOrCreate(['name' => $name])->id);
            $this->trail->record(AuditEvent::Updated, $record, ['tags' => $before], ['tags' => $this->names($record)]);
        });
    }

    public function remove(Model&Taggable $record, string $tagId): void
    {
        $this->db->transaction(function () use ($record, $tagId): void {
            $before = $this->names($record);

            if ($record->tags()->detach($tagId) > 0) {
                $this->trail->record(AuditEvent::Updated, $record, ['tags' => $before], ['tags' => $this->names($record)]);
            }
        });
    }

    /**
     * @return list<string>
     */
    private function names(Model&Taggable $record): array
    {
        return array_values($record->tags()->orderBy('name')->get()->map(fn (Tag $tag): string => $tag->name)->all());
    }
}
