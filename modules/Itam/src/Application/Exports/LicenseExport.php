<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Application\Exports;

use Generator;
use Modules\Core\Domain\Exports\ExportSheet;
use Modules\Core\Domain\Exports\ExportSource;
use Modules\Core\Domain\Models\Export;
use Modules\Itam\Application\Import\LicenseImport;
use Modules\Itam\Domain\Models\License;

/**
 * Every licence with the seats in use, in the import's columns so the file can be
 * corrected and imported back. Keys are never exported.
 */
final readonly class LicenseExport implements ExportSource
{
    public const string TYPE = 'itam.licenses';

    public function sheets(Export $export): array
    {
        return [
            new ExportSheet(__('itam::licenses.title'), [...LicenseImport::COLUMNS, 'seats_used', 'contract'], $this->rows()),
        ];
    }

    public function filename(Export $export): string
    {
        return 'licenses-'.$export->created_at?->format('Y-m-d');
    }

    /**
     * @return Generator<list<string|int|float|bool|null>>
     */
    private function rows(): Generator
    {
        $query = License::query()->with(['product', 'supplier', 'contract'])->withCount('assignments');

        foreach ($query->lazyById(500) as $license) {
            yield [
                $license->product->name,
                $license->product->publisher,
                $license->name,
                $license->model->value,
                $license->quantity,
                $license->supplier?->name,
                $license->purchase_date?->toDateString(),
                $license->cost,
                $license->currency,
                $license->starts_on?->toDateString(),
                $license->expires_on?->toDateString(),
                $license->notes,
                $license->assignments_count ?? 0,
                $license->contract?->title,
            ];
        }
    }
}
