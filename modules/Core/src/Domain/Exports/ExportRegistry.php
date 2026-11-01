<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Exports;

use InvalidArgumentException;

/**
 * Export types offered by the modules, each with the permission needed to request
 * and to download it.
 */
final class ExportRegistry
{
    /** @var array<string, array{source: class-string<ExportSource>, permission: string}> */
    private array $types = [];

    /**
     * @param  class-string<ExportSource>  $source
     */
    public function register(string $type, string $source, string $permission): void
    {
        $this->types[$type] = ['source' => $source, 'permission' => $permission];
    }

    public function has(string $type): bool
    {
        return isset($this->types[$type]);
    }

    /**
     * @return class-string<ExportSource>
     */
    public function source(string $type): string
    {
        return $this->definition($type)['source'];
    }

    public function permission(string $type): string
    {
        return $this->definition($type)['permission'];
    }

    /**
     * @return array{source: class-string<ExportSource>, permission: string}
     */
    private function definition(string $type): array
    {
        return $this->types[$type] ?? throw new InvalidArgumentException("Unknown export type [{$type}].");
    }
}
