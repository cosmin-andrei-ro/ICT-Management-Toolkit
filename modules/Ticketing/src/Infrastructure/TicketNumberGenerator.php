<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Infrastructure;

use Illuminate\Database\ConnectionInterface;
use Modules\Ticketing\Domain\Enums\TicketType;
use RuntimeException;

/**
 * Gap-tolerant, collision-free numbers per organization and type (INC-000042).
 * A single upsert increments atomically, so concurrent requests never share a number.
 */
final readonly class TicketNumberGenerator
{
    public function __construct(private ConnectionInterface $db) {}

    public function next(string $organizationId, TicketType $type): string
    {
        $row = $this->db->selectOne(<<<'SQL'
            INSERT INTO ticketing_sequences (organization_id, type, last_value) VALUES (?, ?, 1)
            ON CONFLICT (organization_id, type) DO UPDATE SET last_value = ticketing_sequences.last_value + 1
            RETURNING last_value
            SQL, [$organizationId, $type->value]);

        $value = is_object($row) ? ($row->last_value ?? null) : null;

        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            throw new RuntimeException('Could not allocate a ticket number.');
        }

        return sprintf('%s-%06d', $type->numberPrefix(), (int) $value);
    }
}
