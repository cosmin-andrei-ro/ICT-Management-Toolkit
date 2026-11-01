<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Exports;

/**
 * One table of an export: a worksheet in XLSX. CSV holds the first sheet only.
 */
final readonly class ExportSheet
{
    /**
     * @param  list<string>  $headings
     * @param  iterable<list<string|int|float|bool|null>>  $rows  Dates as formatted text, in the display time zone.
     */
    public function __construct(
        public string $name,
        public array $headings,
        public iterable $rows,
    ) {}
}
