<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Audit;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Takes fields out of a record's audit entries once its content was removed
 * (an anonymized ticket, ADR 0060), so the text does not outlive it in the
 * log. Who changed the record and when stays.
 */
final readonly class AuditRedaction
{
    public function __construct(private ConnectionInterface $db) {}

    /**
     * @param  list<string>  $fields  Attribute names, as the model logs them.
     * @return int Entries changed.
     */
    public function removeFields(Model $record, array $fields): int
    {
        foreach ($fields as $field) {
            if (preg_match('/^[a-z_][a-z0-9_]*$/', $field) !== 1) {
                throw new InvalidArgumentException("Not an attribute name: [{$field}].");
            }
        }

        $result = $this->db->selectOne('SELECT core_redact_record_in_audit_log(?, ?::uuid, ?::text[]) AS redacted', [
            $record->getMorphClass(), $record->getKey(), '{'.implode(',', $fields).'}',
        ]);

        return is_object($result) && is_numeric($result->redacted ?? null) ? (int) $result->redacted : 0;
    }
}
