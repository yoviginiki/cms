<?php

namespace App\Domain\Collections\Services;

use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Streaming row access over an uploaded CSV or XLSX file (openspout — memory
 * stays flat for 50k-row files). CSV delimiter is sniffed (comma/semicolon/
 * tab), XLSX reads the first sheet. Cells normalize to trimmed strings;
 * XLSX date cells become Y-m-d.
 */
class SpreadsheetReader
{
    /** @return \Generator<int, array<int, string>> zero-based row arrays */
    public function rows(string $path): \Generator
    {
        $reader = $this->openReader($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    yield array_map([$this, 'normalizeCell'], $row->toArray());
                }
                break; // first sheet only
            }
        } finally {
            $reader->close();
        }
    }

    /** Count data rows (excluding the header) for progress math. */
    public function countRows(string $path): int
    {
        $count = 0;
        foreach ($this->rows($path) as $row) {
            $count++;
        }

        return max(0, $count - 1);
    }

    private function openReader(string $path): CsvReader|XlsxReader
    {
        if (str_ends_with(mb_strtolower($path), '.xlsx')) {
            $reader = new XlsxReader();
            $reader->open($path);

            return $reader;
        }

        $options = new CsvOptions();
        $options->FIELD_DELIMITER = $this->sniffDelimiter($path);
        $reader = new CsvReader($options);
        $reader->open($path);

        return $reader;
    }

    private function sniffDelimiter(string $path): string
    {
        $handle = fopen($path, 'r');
        $line = $handle ? (string) fgets($handle, 8192) : '';
        if ($handle) {
            fclose($handle);
        }

        $counts = [
            ',' => substr_count($line, ','),
            ';' => substr_count($line, ';'),
            "\t" => substr_count($line, "\t"),
        ];
        arsort($counts);

        $best = array_key_first($counts);

        return $counts[$best] > 0 ? $best : ',';
    }

    private function normalizeCell(mixed $cell): string
    {
        if ($cell instanceof \DateTimeInterface) {
            return $cell->format('Y-m-d');
        }
        if (is_bool($cell)) {
            return $cell ? 'true' : 'false';
        }

        return trim((string) $cell);
    }
}
