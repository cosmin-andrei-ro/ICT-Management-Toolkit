<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Imports;

/**
 * One row of an import file and what will happen to it.
 */
final readonly class ImportRow
{
    public const string CREATE = 'create';

    public const string UPDATE = 'update';

    public const string UNCHANGED = 'unchanged';

    public const string ERROR = 'error';

    /**
     * @param  list<string>  $errors
     */
    public function __construct(
        public int $line,
        public string $label,
        public string $action,
        public array $errors = [],
    ) {}

    /**
     * @param  array<string, string|int>  $replace
     */
    public static function message(string $key, array $replace = []): string
    {
        $message = __($key, $replace);

        return is_string($message) ? $message : $key;
    }

    /**
     * @return array{line: int, label: string, action: string, errors: list<string>}
     */
    public function toArray(): array
    {
        return ['line' => $this->line, 'label' => $this->label, 'action' => $this->action, 'errors' => $this->errors];
    }
}
