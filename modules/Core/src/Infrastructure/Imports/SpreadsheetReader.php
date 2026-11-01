<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Imports;

use DateTimeInterface;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Reads the first sheet of a CSV or XLSX file into rows keyed by the lowercase
 * header of the first row. Values are trimmed strings; empty rows are skipped.
 */
final class SpreadsheetReader
{
    /**
     * @param  'csv'|'xlsx'  $format
     * @return list<array{line: int, values: array<string, string>}>
     *
     * @throws SpreadsheetUnreadable when the file has no header or too many rows
     */
    public function read(string $path, string $format, int $maxRows): array
    {
        $reader = $format === 'xlsx' ? new XlsxReader : new CsvReader($this->csvOptions($path));
        $reader->open($path);

        try {
            $headers = null;
            $rows = [];

            foreach ($reader->getSheetIterator() as $sheet) {
                $line = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    $line++;
                    $cells = array_map(self::text(...), $row->toArray());

                    if ($headers === null) {
                        $headers = array_map(fn (string $header): string => mb_strtolower($header), $cells);

                        continue;
                    }

                    if (implode('', $cells) === '') {
                        continue;
                    }

                    if (count($rows) >= $maxRows) {
                        throw new SpreadsheetUnreadable('too_many_rows');
                    }

                    $values = [];

                    foreach ($headers as $index => $header) {
                        if ($header !== '') {
                            $values[$header] = $cells[$index] ?? '';
                        }
                    }

                    $rows[] = ['line' => $line, 'values' => $values];
                }

                // The first sheet only.
                break;
            }
        } finally {
            $reader->close();
        }

        if ($headers === null || array_filter($headers) === []) {
            throw new SpreadsheetUnreadable('no_header');
        }

        return $rows;
    }

    /**
     * Excel saves CSV with ";" where the decimal separator is a comma (e.g. Romanian settings).
     */
    private function csvOptions(string $path): CsvOptions
    {
        $options = new CsvOptions;
        $handle = fopen($path, 'rb');
        $firstLine = $handle !== false ? (string) fgets($handle) : '';

        if ($handle !== false) {
            fclose($handle);
        }

        $options->FIELD_DELIMITER = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        return $options;
    }

    private static function text(mixed $value): string
    {
        return match (true) {
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            is_bool($value) => $value ? '1' : '0',
            is_scalar($value) => trim((string) $value),
            default => '',
        };
    }
}
