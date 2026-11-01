<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Exports;

use Modules\Core\Domain\Models\Export;

/**
 * What a module exports. Runs in the queue, in the export's organization.
 */
interface ExportSource
{
    /**
     * @return non-empty-list<ExportSheet> The first sheet is the one written to CSV.
     */
    public function sheets(Export $export): array;

    /**
     * Download name without extension, e.g. "tickets-2026-10-01-2026-10-31".
     */
    public function filename(Export $export): string;
}
