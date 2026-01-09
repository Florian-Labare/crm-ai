<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ImportFileParserService
{
    private const SUPPORTED_EXTENSIONS = ['xlsx', 'xls', 'csv'];
    private const CHUNK_SIZE = 100;

    public function parseFile(string $filePath): array
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (!in_array($extension, self::SUPPORTED_EXTENSIONS)) {
            throw new \InvalidArgumentException("Format de fichier non supporté: {$extension}");
        }

        if ($extension === 'csv') {
            return $this->parseCsv($filePath);
        }

        return $this->parseExcel($filePath);
    }

    public function detectColumns(string $filePath): array
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($extension === 'csv') {
            return $this->detectCsvColumns($filePath);
        }

        return $this->detectExcelColumns($filePath);
    }

    public function getRowCount(string $filePath): int
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($extension === 'csv') {
            return $this->getCsvRowCount($filePath);
        }

        return $this->getExcelRowCount($filePath);
    }

    public function parseChunk(string $filePath, int $offset, int $limit = self::CHUNK_SIZE): array
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($extension === 'csv') {
            return $this->parseCsvChunk($filePath, $offset, $limit);
        }

        return $this->parseExcelChunk($filePath, $offset, $limit);
    }

    private function parseExcel(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $data = $worksheet->toArray(null, true, true, true);

        if (empty($data)) {
            return ['headers' => [], 'rows' => []];
        }

        $headers = array_shift($data);
        $headers = array_map(fn($h) => trim((string) $h), $headers);

        $rows = [];
        foreach ($data as $rowIndex => $row) {
            $rowData = [];
            foreach ($headers as $colKey => $header) {
                if (!empty($header)) {
                    $rowData[$header] = $row[$colKey] ?? null;
                }
            }
            if ($this->isRowNotEmpty($rowData)) {
                $rows[] = $rowData;
            }
        }

        return ['headers' => array_filter($headers), 'rows' => $rows];
    }

    private function parseCsv(string $filePath): array
    {
        $encoding = $this->detectEncoding($filePath);
        $delimiter = $this->detectCsvDelimiter($filePath);

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Impossible d'ouvrir le fichier: {$filePath}");
        }

        $headers = fgetcsv($handle, 0, $delimiter);
        if ($headers === false) {
            fclose($handle);
            return ['headers' => [], 'rows' => []];
        }

        $headers = array_map(fn($h) => $this->convertEncoding(trim($h), $encoding), $headers);

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowData = [];
            foreach ($headers as $index => $header) {
                if (!empty($header)) {
                    $value = $row[$index] ?? null;
                    $rowData[$header] = $value !== null ? $this->convertEncoding($value, $encoding) : null;
                }
            }
            if ($this->isRowNotEmpty($rowData)) {
                $rows[] = $rowData;
            }
        }

        fclose($handle);

        return ['headers' => array_filter($headers), 'rows' => $rows];
    }

    private function detectExcelColumns(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $firstRow = $worksheet->rangeToArray('A1:Z1', null, true, true, true)[1] ?? [];

        return array_values(array_filter(array_map(fn($h) => trim((string) $h), $firstRow)));
    }

    private function detectCsvColumns(string $filePath): array
    {
        $encoding = $this->detectEncoding($filePath);
        $delimiter = $this->detectCsvDelimiter($filePath);

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle, 0, $delimiter);
        fclose($handle);

        if ($headers === false) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn($h) => $this->convertEncoding(trim($h), $encoding),
            $headers
        )));
    }

    private function getExcelRowCount(string $filePath): int
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();

        return max(0, $worksheet->getHighestRow() - 1);
    }

    private function getCsvRowCount(string $filePath): int
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return 0;
        }

        $count = 0;
        fgetcsv($handle);

        while (fgetcsv($handle) !== false) {
            $count++;
        }

        fclose($handle);

        return $count;
    }

    private function parseExcelChunk(string $filePath, int $offset, int $limit): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();

        $headersRow = $worksheet->rangeToArray('A1:Z1', null, true, true, true)[1] ?? [];
        $headers = array_map(fn($h) => trim((string) $h), $headersRow);

        $startRow = $offset + 2;
        $endRow = $startRow + $limit - 1;
        $highestRow = $worksheet->getHighestRow();

        $endRow = min($endRow, $highestRow);

        $rows = [];
        for ($rowNum = $startRow; $rowNum <= $endRow; $rowNum++) {
            $rowData = [];
            foreach ($headers as $colKey => $header) {
                if (!empty($header)) {
                    $rowData[$header] = $worksheet->getCell($colKey . $rowNum)->getValue();
                }
            }
            if ($this->isRowNotEmpty($rowData)) {
                $rows[] = $rowData;
            }
        }

        return $rows;
    }

    private function parseCsvChunk(string $filePath, int $offset, int $limit): array
    {
        $encoding = $this->detectEncoding($filePath);
        $delimiter = $this->detectCsvDelimiter($filePath);

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle, 0, $delimiter);
        if ($headers === false) {
            fclose($handle);
            return [];
        }

        $headers = array_map(fn($h) => $this->convertEncoding(trim($h), $encoding), $headers);

        $currentRow = 0;
        while ($currentRow < $offset && fgetcsv($handle, 0, $delimiter) !== false) {
            $currentRow++;
        }

        $rows = [];
        $count = 0;
        while ($count < $limit && ($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowData = [];
            foreach ($headers as $index => $header) {
                if (!empty($header)) {
                    $value = $row[$index] ?? null;
                    $rowData[$header] = $value !== null ? $this->convertEncoding($value, $encoding) : null;
                }
            }
            if ($this->isRowNotEmpty($rowData)) {
                $rows[] = $rowData;
            }
            $count++;
        }

        fclose($handle);

        return $rows;
    }

    private function detectEncoding(string $filePath): string
    {
        $content = file_get_contents($filePath, false, null, 0, 1024);

        if ($content === false) {
            return 'UTF-8';
        }

        $bom = substr($content, 0, 3);
        if ($bom === "\xEF\xBB\xBF") {
            return 'UTF-8';
        }

        $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);

        return $encoding ?: 'UTF-8';
    }

    private function detectCsvDelimiter(string $filePath): string
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return ',';
        }

        $line = fgets($handle, 4096);
        fclose($handle);

        if ($line === false) {
            return ',';
        }

        $delimiters = [
            ',' => substr_count($line, ','),
            ';' => substr_count($line, ';'),
            "\t" => substr_count($line, "\t"),
            '|' => substr_count($line, '|'),
        ];

        return array_search(max($delimiters), $delimiters) ?: ',';
    }

    private function convertEncoding(string $value, string $fromEncoding): string
    {
        if ($fromEncoding === 'UTF-8') {
            return $value;
        }

        $converted = mb_convert_encoding($value, 'UTF-8', $fromEncoding);

        return $converted !== false ? $converted : $value;
    }

    private function isRowNotEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }
}
