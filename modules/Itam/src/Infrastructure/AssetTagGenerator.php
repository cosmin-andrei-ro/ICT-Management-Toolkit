<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Infrastructure;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\ConnectionInterface;
use RuntimeException;

/**
 * Asset tags per organization (AST-000042). A single upsert increments atomically,
 * so concurrent requests never share a number; numbers already taken by hand
 * (labels from an older inventory) are skipped.
 */
final readonly class AssetTagGenerator
{
    public function __construct(
        private ConnectionInterface $db,
        private Config $config,
    ) {}

    public function next(string $organizationId): string
    {
        $prefix = $this->config->get('itam.assets.tag_prefix');
        $prefix = is_string($prefix) && preg_match('/^[A-Z0-9]{1,10}$/', $prefix) === 1 ? $prefix : 'AST';

        for ($attempt = 0; $attempt < 100; $attempt++) {
            $tag = sprintf('%s-%06d', $prefix, $this->increment($organizationId));
            $taken = $this->db->table('itam_assets')
                ->where('organization_id', $organizationId)
                ->whereNull('deleted_at')
                ->whereRaw('lower(asset_tag) = ?', [strtolower($tag)])
                ->exists();

            if (! $taken) {
                return $tag;
            }
        }

        throw new RuntimeException('Could not allocate a free asset tag.');
    }

    private function increment(string $organizationId): int
    {
        $row = $this->db->selectOne(<<<'SQL'
            INSERT INTO itam_tag_sequences (organization_id, last_value) VALUES (?, 1)
            ON CONFLICT (organization_id) DO UPDATE SET last_value = itam_tag_sequences.last_value + 1
            RETURNING last_value
            SQL, [$organizationId]);

        $value = is_object($row) ? ($row->last_value ?? null) : null;

        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            throw new RuntimeException('Could not allocate an asset tag.');
        }

        return (int) $value;
    }
}
