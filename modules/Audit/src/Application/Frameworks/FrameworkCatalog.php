<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Application\Frameworks;

use InvalidArgumentException;

/**
 * The frameworks shipped with the toolkit (owner's decision): ISO/IEC 27001
 * Annex A (identifiers and own titles only), NIS2, GDPR and DORA, read from
 * resources/frameworks.
 */
final class FrameworkCatalog
{
    public const array KEYS = ['iso27001', 'nis2', 'gdpr', 'dora'];

    /**
     * @return array{key: string, name: string, version: string, has_soa: bool, description: string, requirements: list<array{reference: string, title: string, description: ?string, group: string}>}
     */
    public static function get(string $key): array
    {
        if (! in_array($key, self::KEYS, true)) {
            throw new InvalidArgumentException('Unknown framework.');
        }

        /** @var array{key: string, name: string, version: string, has_soa: bool, description: string, groups: array<string, array<string, string|array{0: string, 1: string}>>} $data */
        $data = require dirname(__DIR__, 3).'/resources/frameworks/'.$key.'.php';
        $requirements = [];

        foreach ($data['groups'] as $group => $entries) {
            foreach ($entries as $reference => $entry) {
                $requirements[] = [
                    'reference' => (string) $reference,
                    'title' => is_array($entry) ? $entry[0] : $entry,
                    'description' => is_array($entry) ? $entry[1] : null,
                    'group' => $group,
                ];
            }
        }

        return [
            'key' => $data['key'],
            'name' => $data['name'],
            'version' => $data['version'],
            'has_soa' => $data['has_soa'],
            'description' => $data['description'],
            'requirements' => $requirements,
        ];
    }

    /**
     * @return array<string, string> Key => name.
     */
    public static function available(): array
    {
        $names = [];

        foreach (self::KEYS as $key) {
            $names[$key] = self::get($key)['name'];
        }

        return $names;
    }
}
