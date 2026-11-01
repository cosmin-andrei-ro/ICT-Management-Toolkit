<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Exports;

use Modules\Core\Domain\Exports\ExportFormat;
use Modules\Core\Domain\Exports\ExportSheet;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * Writes sheets to a CSV or XLSX file with OpenSpout, streaming row by row.
 */
final class SpreadsheetWriter
{
    /**
     * @param  non-empty-list<ExportSheet>  $sheets
     * @return int Data rows written (headings excluded).
     */
    public function write(string $path, ExportFormat $format, array $sheets): int
    {
        $writer = $format === ExportFormat::Csv ? new CsvWriter : new XlsxWriter;
        $writer->openToFile($path);
        $rows = 0;

        try {
            // CSV has a single table: the first sheet.
            foreach ($format === ExportFormat::Csv ? [$sheets[0]] : $sheets as $index => $sheet) {
                if ($writer instanceof XlsxWriter) {
                    ($index === 0 ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent())->setName(self::sheetName($sheet->name));
                }

                $writer->addRow(Row::fromValues(array_map(self::cell(...), $sheet->headings), (new Style)->setFontBold()));

                foreach ($sheet->rows as $row) {
                    $writer->addRow(Row::fromValues(array_map(self::cell(...), $row)));
                    $rows++;
                }
            }
        } finally {
            $writer->close();
        }

        return $rows;
    }

    /**
     * Neutralizes formula injection: a text starting with = + - @ or a control
     * character would run as a formula when opened in a spreadsheet (OWASP CSV injection).
     */
    public static function cell(string|int|float|bool|null $value): string|int|float|bool|null
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r", "\n"], true)) {
            return "'".$value;
        }

        return $value;
    }

    private static function sheetName(string $name): string
    {
        // Excel forbids \ / ? * [ ] : in sheet names and limits them to 31 characters.
        $name = trim((string) preg_replace(['/[\\\\\/?*\[\]:]+/', '/\s+/'], ' ', $name));

        return mb_substr($name !== '' ? $name : 'Sheet', 0, 31);
    }
}
