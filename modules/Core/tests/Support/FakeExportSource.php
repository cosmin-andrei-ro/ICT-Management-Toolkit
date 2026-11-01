<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Tests\Support;

use Modules\Core\Domain\Exports\ExportSheet;
use Modules\Core\Domain\Exports\ExportSource;
use Modules\Core\Domain\Models\Export;

final class FakeExportSource implements ExportSource
{
    public function sheets(Export $export): array
    {
        return [
            new ExportSheet('Rows', ['Name', 'Amount'], [['=HYPERLINK("http://evil.example","x")', 3], ['Plain', -5], ['+cmd', null]]),
            new ExportSheet('About: [period]', ['Key'], [['value']]),
        ];
    }

    public function filename(Export $export): string
    {
        return 'test-export';
    }
}
