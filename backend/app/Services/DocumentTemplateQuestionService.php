<?php

namespace App\Services;

class DocumentTemplateQuestionService
{
    /**
     * Extrait un mapping variable -> question depuis un template DOCX.
     *
     * @return array<string, string>
     */
    public function extractQuestions(string $templatePath): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($templatePath) !== true) {
            throw new \Exception("Cannot open template file: {$templatePath}");
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            return [];
        }

        $mapping = [];

        $lastLabel = '';
        $this->extractFromRows($xml, $mapping, $lastLabel);
        $this->extractFromParagraphs($xml, $mapping, $lastLabel);

        return $mapping;
    }

    private function extractFromRows(string $xml, array &$mapping, string &$lastLabel): void
    {
        if (!preg_match_all('/<w:tr\\b[^>]*>(.*?)<\\/w:tr>/s', $xml, $rows)) {
            return;
        }

        $headerCells = [];

        foreach ($rows[1] as $rowXml) {
            if (!preg_match_all('/<w:tc\\b[^>]*>(.*?)<\\/w:tc>/s', $rowXml, $cells)) {
                continue;
            }

            $cellTexts = [];
            $cellVariables = [];

            foreach ($cells[1] as $cellXml) {
                $rawText = $this->extractPlainText($cellXml);
                $cellTexts[] = $this->sanitizeLabel($rawText);
                $cellVariables[] = $this->extractVariables($rawText);
            }

            $rowHasVariables = false;
            foreach ($cellVariables as $vars) {
                if (!empty($vars)) {
                    $rowHasVariables = true;
                    break;
                }
            }

            if (!$rowHasVariables) {
                $candidateHeader = array_filter($cellTexts, fn($text) => $this->isUsableLabel($text));
                if (!empty($candidateHeader)) {
                    $headerCells = $cellTexts;
                }
                continue;
            }

            $labelCellText = '';
            foreach ($cellTexts as $index => $text) {
                if (!empty($cellVariables[$index])) {
                    continue;
                }
                if ($this->isUsableLabel($text)) {
                    $labelCellText = $text;
                    break;
                }
            }

            foreach ($cellTexts as $index => $text) {
                $variables = $cellVariables[$index];
                if (empty($variables)) {
                    continue;
                }

                foreach ($variables as $variable) {
                    if (isset($mapping[$variable])) {
                        continue;
                    }

                    $label = $labelCellText !== '' ? $labelCellText : '';
                    if ($label === '' && isset($headerCells[$index]) && $this->isUsableLabel($headerCells[$index])) {
                        $label = $headerCells[$index];
                    }
                    if ($label === '') {
                        $label = $this->findNearestLabelInRow($cellTexts, $cellVariables, $index);
                    }
                    if ($label === '') {
                        $label = $this->deriveLabelFromText($text, $variable);
                    }
                    $label = $this->sanitizeLabel($label);
                    if ($this->isUsableLabel($label)) {
                        $mapping[$variable] = $label;
                        $lastLabel = $label;
                    }
                }
            }
        }
    }

    private function extractFromParagraphs(string $xml, array &$mapping, string &$lastLabel): void
    {
        if (!preg_match_all('/<w:p\\b[^>]*>(.*?)<\\/w:p>/s', $xml, $paragraphs)) {
            return;
        }

        foreach ($paragraphs[1] as $paragraphXml) {
            $rawText = $this->extractPlainText($paragraphXml);
            $text = $this->sanitizeLabel($rawText);
            if ($text === '') {
                continue;
            }

            $variables = $this->extractVariables($rawText);
            if (empty($variables)) {
                if ($this->isUsableLabel($text)) {
                    $lastLabel = $text;
                }
                continue;
            }

            foreach ($variables as $variable) {
                if (isset($mapping[$variable])) {
                    continue;
                }

                $label = $this->sanitizeLabel($this->deriveLabelFromText($text, $variable));
                if (!$this->isUsableLabel($label) && $this->paragraphIsMostlyVariable($text, $variable)) {
                    $label = $lastLabel;
                }
                if ($this->isUsableLabel($label)) {
                    $mapping[$variable] = $label;
                    $lastLabel = $label;
                }
            }
        }
    }

    private function deriveLabelFromText(string $text, string $variable): string
    {
        $needle = '{{' . $variable . '}}';
        $pos = strpos($text, $needle);
        if ($pos === false) {
            return '';
        }

        $before = trim(substr($text, 0, $pos));
        $after = trim(substr($text, $pos + strlen($needle)));

        $label = '';
        if ($before !== '') {
            if (preg_match('/[^\\n\\r\\.?;:]*$/u', $before, $matches)) {
                $label = trim($matches[0]);
            } else {
                $label = $before;
            }
        }

        if ($label === '' && $after !== '') {
            if (preg_match('/^[^\\n\\r\\.?;:]+/u', $after, $matches)) {
                $label = trim($matches[0]);
            } else {
                $label = $after;
            }
        }

        $label = $this->sanitizeLabel($label);
        $label = trim($label, " \t\n\r\0\x0B:-");

        return $label ?? '';
    }

    private function extractPlainText(string $xmlChunk): string
    {
        if (!preg_match_all('/<w:t[^>]*>(.*?)<\\/w:t>/s', $xmlChunk, $textMatches)) {
            return '';
        }

        $text = implode('', $textMatches[1]);
        $text = html_entity_decode($text, ENT_XML1);
        $text = preg_replace('/<[^>]+>/', '', $text);
        $text = preg_replace('/\\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * @return string[]
     */
    private function extractVariables(string $text): array
    {
        if (!preg_match_all('/\\{\\{([^}]+)\\}\\}/', $text, $varMatches)) {
            return [];
        }

        $variables = array_map('trim', $varMatches[1]);
        $variables = array_filter($variables, fn($v) => $v !== '');

        return array_values(array_unique($variables));
    }

    private function isUsableLabel(string $label): bool
    {
        if ($label === '') {
            return false;
        }

        if (str_contains($label, '{{') || str_contains($label, '}}')) {
            return false;
        }

        if ($label === 't>' || $label === 't') {
            return false;
        }

        return true;
    }

    private function findNearestLabelInRow(array $cellTexts, array $cellVariables, int $targetIndex): string
    {
        $bestLabel = '';
        $bestDistance = PHP_INT_MAX;

        foreach ($cellTexts as $index => $text) {
            if ($index === $targetIndex) {
                continue;
            }
            if (!empty($cellVariables[$index])) {
                continue;
            }
            if (!$this->isUsableLabel($text)) {
                continue;
            }

            $distance = abs($targetIndex - $index);
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $bestLabel = $text;
            }
        }

        if ($bestLabel !== '') {
            return $bestLabel;
        }

        $rowText = implode(' ', array_filter($cellTexts, fn($text) => $this->isUsableLabel($text)));
        $rowText = trim(preg_replace('/\\s+/', ' ', $rowText));

        return $rowText;
    }

    private function paragraphIsMostlyVariable(string $text, string $variable): bool
    {
        $needle = '{{' . $variable . '}}';
        $cleaned = trim(str_replace($needle, '', $text));

        return $cleaned === '' || strlen($cleaned) < 3;
    }

    private function sanitizeLabel(string $label): string
    {
        if ($label === '') {
            return '';
        }

        $label = preg_replace('/\\{\\{[^}]+\\}\\}/', '', $label);
        $label = preg_replace('/\\s+/', ' ', $label);
        $label = trim($label);

        return $label;
    }
}
