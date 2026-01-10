<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportFileParserService
{
    private const SUPPORTED_EXTENSIONS = ['xlsx', 'xls', 'csv', 'json', 'xml', 'sql'];
    private const CHUNK_SIZE = 100;

    public function getSupportedFormats(): array
    {
        return [
            'xlsx' => 'Microsoft Excel (.xlsx)',
            'xls' => 'Microsoft Excel 97-2003 (.xls)',
            'csv' => 'CSV - Valeurs séparées par virgules (.csv)',
            'json' => 'JSON - JavaScript Object Notation (.json)',
            'xml' => 'XML - eXtensible Markup Language (.xml)',
            'sql' => 'SQL - Dump de base de données (.sql)',
        ];
    }

    public function isSupported(string $filePath): bool
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return in_array($extension, self::SUPPORTED_EXTENSIONS);
    }

    public function parseFile(string $filePath): array
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (!in_array($extension, self::SUPPORTED_EXTENSIONS)) {
            throw new \InvalidArgumentException("Format de fichier non supporté: {$extension}. Formats acceptés: " . implode(', ', self::SUPPORTED_EXTENSIONS));
        }

        return match ($extension) {
            'csv' => $this->parseCsv($filePath),
            'json' => $this->parseJson($filePath),
            'xml' => $this->parseXml($filePath),
            'sql' => $this->parseSql($filePath),
            default => $this->parseExcel($filePath),
        };
    }

    public function detectColumns(string $filePath): array
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv' => $this->detectCsvColumns($filePath),
            'json' => $this->detectJsonColumns($filePath),
            'xml' => $this->detectXmlColumns($filePath),
            'sql' => $this->detectSqlColumns($filePath),
            default => $this->detectExcelColumns($filePath),
        };
    }

    public function getRowCount(string $filePath): int
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv' => $this->getCsvRowCount($filePath),
            'json' => $this->getJsonRowCount($filePath),
            'xml' => $this->getXmlRowCount($filePath),
            'sql' => $this->getSqlRowCount($filePath),
            default => $this->getExcelRowCount($filePath),
        };
    }

    public function parseChunk(string $filePath, int $offset, int $limit = self::CHUNK_SIZE): array
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv' => $this->parseCsvChunk($filePath, $offset, $limit),
            'json' => $this->parseJsonChunk($filePath, $offset, $limit),
            'xml' => $this->parseXmlChunk($filePath, $offset, $limit),
            'sql' => $this->parseSqlChunk($filePath, $offset, $limit),
            default => $this->parseExcelChunk($filePath, $offset, $limit),
        };
    }

    // ==================== EXCEL ====================

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
        foreach ($data as $row) {
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

    private function detectExcelColumns(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $firstRow = $worksheet->rangeToArray('A1:AZ1', null, true, true, true)[1] ?? [];

        return array_values(array_filter(array_map(fn($h) => trim((string) $h), $firstRow)));
    }

    private function getExcelRowCount(string $filePath): int
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();

        return max(0, $worksheet->getHighestRow() - 1);
    }

    private function parseExcelChunk(string $filePath, int $offset, int $limit): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();

        $headersRow = $worksheet->rangeToArray('A1:AZ1', null, true, true, true)[1] ?? [];
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

    // ==================== CSV ====================

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

    // ==================== JSON ====================

    private function parseJson(string $filePath): array
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException("Impossible de lire le fichier: {$filePath}");
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("JSON invalide: " . json_last_error_msg());
        }

        // Handle different JSON structures
        $rows = $this->normalizeJsonData($data);

        if (empty($rows)) {
            return ['headers' => [], 'rows' => []];
        }

        // Extract headers from first row
        $headers = array_keys($rows[0]);

        return ['headers' => $headers, 'rows' => $rows];
    }

    private function normalizeJsonData(mixed $data): array
    {
        // If it's already an array of objects
        if (is_array($data) && !empty($data)) {
            // Check if it's an array of arrays/objects
            if (isset($data[0]) && (is_array($data[0]) || is_object($data[0]))) {
                return array_map(fn($item) => (array) $item, $data);
            }

            // Check for common wrapper keys
            $wrapperKeys = ['data', 'results', 'items', 'records', 'clients', 'rows', 'entries'];
            foreach ($wrapperKeys as $key) {
                if (isset($data[$key]) && is_array($data[$key])) {
                    return array_map(fn($item) => (array) $item, $data[$key]);
                }
            }

            // Single object - wrap in array
            if (!isset($data[0])) {
                return [(array) $data];
            }
        }

        return [];
    }

    private function detectJsonColumns(string $filePath): array
    {
        $content = file_get_contents($filePath, false, null, 0, 65536); // Read first 64KB
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        $rows = $this->normalizeJsonData($data);
        if (empty($rows)) {
            return [];
        }

        // Get all unique keys from first 10 rows
        $headers = [];
        foreach (array_slice($rows, 0, 10) as $row) {
            $headers = array_merge($headers, array_keys((array) $row));
        }

        return array_values(array_unique($headers));
    }

    private function getJsonRowCount(string $filePath): int
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return 0;
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return 0;
        }

        $rows = $this->normalizeJsonData($data);
        return count($rows);
    }

    private function parseJsonChunk(string $filePath, int $offset, int $limit): array
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        $rows = $this->normalizeJsonData($data);
        return array_slice($rows, $offset, $limit);
    }

    // ==================== XML ====================

    private function parseXml(string $filePath): array
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException("Impossible de lire le fichier: {$filePath}");
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);

        if ($xml === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            throw new \RuntimeException("XML invalide: " . ($errors[0]->message ?? 'Erreur inconnue'));
        }

        $rows = $this->normalizeXmlData($xml);

        if (empty($rows)) {
            return ['headers' => [], 'rows' => []];
        }

        $headers = array_keys($rows[0]);

        return ['headers' => $headers, 'rows' => $rows];
    }

    private function normalizeXmlData(\SimpleXMLElement $xml): array
    {
        $rows = [];

        // Try to find record elements (first level children)
        foreach ($xml->children() as $child) {
            $row = $this->xmlElementToArray($child);
            if (!empty($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function xmlElementToArray(\SimpleXMLElement $element): array
    {
        $result = [];

        // Get attributes
        foreach ($element->attributes() as $name => $value) {
            $result[(string) $name] = (string) $value;
        }

        // Get child elements
        foreach ($element->children() as $name => $child) {
            $childArray = $this->xmlElementToArray($child);
            if (empty($childArray)) {
                $result[(string) $name] = (string) $child;
            } else {
                // Flatten nested objects with dot notation
                foreach ($childArray as $key => $value) {
                    $result["{$name}.{$key}"] = $value;
                }
            }
        }

        // If no children, get text content
        if (empty($result) && strlen(trim((string) $element)) > 0) {
            return ['value' => (string) $element];
        }

        return $result;
    }

    private function detectXmlColumns(string $filePath): array
    {
        $content = file_get_contents($filePath, false, null, 0, 65536);
        if ($content === false) {
            return [];
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        libxml_clear_errors();

        if ($xml === false) {
            return [];
        }

        $rows = $this->normalizeXmlData($xml);
        if (empty($rows)) {
            return [];
        }

        $headers = [];
        foreach (array_slice($rows, 0, 10) as $row) {
            $headers = array_merge($headers, array_keys($row));
        }

        return array_values(array_unique($headers));
    }

    private function getXmlRowCount(string $filePath): int
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return 0;
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        libxml_clear_errors();

        if ($xml === false) {
            return 0;
        }

        return count($xml->children());
    }

    private function parseXmlChunk(string $filePath, int $offset, int $limit): array
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return [];
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        libxml_clear_errors();

        if ($xml === false) {
            return [];
        }

        $rows = $this->normalizeXmlData($xml);
        return array_slice($rows, $offset, $limit);
    }

    // ==================== SQL ====================

    private function parseSql(string $filePath): array
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException("Impossible de lire le fichier: {$filePath}");
        }

        $rows = $this->extractInsertStatements($content);

        if (empty($rows)) {
            return ['headers' => [], 'rows' => []];
        }

        $headers = array_keys($rows[0]);

        return ['headers' => $headers, 'rows' => $rows];
    }

    private function extractInsertStatements(string $sql): array
    {
        $rows = [];

        // Match INSERT INTO statements
        // Pattern: INSERT INTO `table` (`col1`, `col2`) VALUES ('val1', 'val2');
        $pattern = '/INSERT\s+INTO\s+[`"\']?(\w+)[`"\']?\s*\(([^)]+)\)\s*VALUES\s*(.+?);/is';

        if (preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $columns = $this->parseSqlColumns($match[2]);
                $valuesString = $match[3];

                // Parse multiple value sets: (val1, val2), (val3, val4)
                $valueSets = $this->parseSqlValueSets($valuesString);

                foreach ($valueSets as $values) {
                    if (count($values) === count($columns)) {
                        $rows[] = array_combine($columns, $values);
                    }
                }
            }
        }

        return $rows;
    }

    private function parseSqlColumns(string $columnsStr): array
    {
        $columns = [];
        $parts = explode(',', $columnsStr);

        foreach ($parts as $part) {
            $col = trim($part);
            $col = trim($col, '`"\' ');
            if (!empty($col)) {
                $columns[] = $col;
            }
        }

        return $columns;
    }

    private function parseSqlValueSets(string $valuesStr): array
    {
        $sets = [];
        $current = '';
        $inString = false;
        $stringChar = '';
        $parenDepth = 0;

        for ($i = 0; $i < strlen($valuesStr); $i++) {
            $char = $valuesStr[$i];
            $prevChar = $i > 0 ? $valuesStr[$i - 1] : '';

            if ($inString) {
                $current .= $char;
                if ($char === $stringChar && $prevChar !== '\\') {
                    $inString = false;
                }
            } else {
                if ($char === "'" || $char === '"') {
                    $inString = true;
                    $stringChar = $char;
                    $current .= $char;
                } elseif ($char === '(') {
                    $parenDepth++;
                    if ($parenDepth === 1) {
                        $current = '';
                    } else {
                        $current .= $char;
                    }
                } elseif ($char === ')') {
                    $parenDepth--;
                    if ($parenDepth === 0) {
                        $values = $this->parseSqlValues($current);
                        if (!empty($values)) {
                            $sets[] = $values;
                        }
                        $current = '';
                    } else {
                        $current .= $char;
                    }
                } else {
                    $current .= $char;
                }
            }
        }

        return $sets;
    }

    private function parseSqlValues(string $valuesStr): array
    {
        $values = [];
        $current = '';
        $inString = false;
        $stringChar = '';

        for ($i = 0; $i < strlen($valuesStr); $i++) {
            $char = $valuesStr[$i];
            $prevChar = $i > 0 ? $valuesStr[$i - 1] : '';

            if ($inString) {
                if ($char === $stringChar && $prevChar !== '\\') {
                    $inString = false;
                } else {
                    $current .= $char;
                }
            } else {
                if ($char === "'" || $char === '"') {
                    $inString = true;
                    $stringChar = $char;
                } elseif ($char === ',') {
                    $values[] = $this->cleanSqlValue($current);
                    $current = '';
                } else {
                    $current .= $char;
                }
            }
        }

        // Don't forget the last value
        $values[] = $this->cleanSqlValue($current);

        return $values;
    }

    private function cleanSqlValue(string $value): ?string
    {
        $value = trim($value);

        if (strtoupper($value) === 'NULL') {
            return null;
        }

        // Remove surrounding quotes
        $value = trim($value, "'\"`");

        // Unescape
        $value = str_replace(['\\\'', '\\"', '\\\\'], ["'", '"', '\\'], $value);

        return $value;
    }

    private function detectSqlColumns(string $filePath): array
    {
        $content = file_get_contents($filePath, false, null, 0, 65536);
        if ($content === false) {
            return [];
        }

        // Find first INSERT statement
        $pattern = '/INSERT\s+INTO\s+[`"\']?\w+[`"\']?\s*\(([^)]+)\)/i';
        if (preg_match($pattern, $content, $match)) {
            return $this->parseSqlColumns($match[1]);
        }

        return [];
    }

    private function getSqlRowCount(string $filePath): int
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return 0;
        }

        $rows = $this->extractInsertStatements($content);
        return count($rows);
    }

    private function parseSqlChunk(string $filePath, int $offset, int $limit): array
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return [];
        }

        $rows = $this->extractInsertStatements($content);
        return array_slice($rows, $offset, $limit);
    }

    // ==================== HELPERS ====================

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
