<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Api;

/**
 * Token abilities, registered by modules. Abilities narrow a token; the user's
 * permissions still apply to every request.
 */
final class ApiAbility
{
    /** @var array<string, string> ability => description translation key */
    private static array $abilities = [];

    public static function register(string $ability, string $description): void
    {
        self::$abilities[$ability] = $description;
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(self::$abilities);
    }
}
