<?php

class WhatsappReportExportService {
    public static function downloadCsv(string $title, array $rows, string $filename): void {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . self::safeFilename($filename, 'csv') . '"');
        header('Cache-Control: private, no-store, max-age=0');

        echo self::buildCsv($title, $rows);
        exit;
    }

    public static function buildCsv(string $title, array $rows): string {
        $output = fopen('php://temp', 'r+');
        if ($output === false) {
            throw new RuntimeException('Unable to open the report output stream.');
        }

        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, [$title]);
        fputcsv($output, ['Generated', date('Y-m-d H:i:s')]);
        fputcsv($output, []);

        $columns = $rows ? array_keys($rows[0]) : ['No records'];
        fputcsv($output, array_map([self::class, 'humanizeColumn'], $columns));
        foreach ($rows as $row) {
            fputcsv($output, array_map(
                static fn(string $column) => self::csvSafeValue($row[$column] ?? ''),
                $columns
            ));
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        if ($csv === false) {
            throw new RuntimeException('Unable to read the generated CSV report.');
        }
        return $csv;
    }

    public static function downloadPdf(string $title, array $rows, string $filename): void {
        $pdf = self::buildPdf($title, $rows);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . self::safeFilename($filename, 'pdf') . '"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, no-store, max-age=0');
        echo $pdf;
        exit;
    }

    public static function buildPdf(string $title, array $rows): string {
        $columns = $rows ? array_keys($rows[0]) : ['No records'];
        $columnGroups = array_chunk($columns, 6);
        $pages = [];
        $pageBottom = 34;
        $tableTop = 524;
        $headerHeight = 25;
        $lineHeight = 9;
        $maxCellLines = (int)floor(($tableTop - $headerHeight - $pageBottom - 8) / $lineHeight);

        foreach ($columnGroups as $groupIndex => $group) {
            $page = ['group' => $group, 'group_index' => $groupIndex, 'rows' => [], 'used_height' => 0];
            foreach ($rows as $rowIndex => $row) {
                $wrappedCells = [];
                foreach ($group as $column) {
                    $value = self::pdfText(self::valueToString($row[$column] ?? ''));
                    $columnWidth = (842 - 56) / (count($group) + 1);
                    $maxChars = max(8, (int)floor(($columnWidth - 10) / 3.8));
                    $wrappedCells[] = self::wrapPdfText($value, $maxChars);
                }
                $lineCount = max(array_map('count', $wrappedCells) ?: [1]);
                for ($lineOffset = 0; $lineOffset < $lineCount; $lineOffset += $maxCellLines) {
                    $segmentLines = min($maxCellLines, $lineCount - $lineOffset);
                    $rowHeight = 7 + ($segmentLines * $lineHeight);
                    $availableHeight = $tableTop - $headerHeight - $pageBottom;
                    if ($page['used_height'] + $rowHeight > $availableHeight) {
                        $pages[] = $page;
                        $page = ['group' => $group, 'group_index' => $groupIndex, 'rows' => [], 'used_height' => 0];
                    }
                    $page['rows'][] = [
                        'index' => $rowIndex + 1,
                        'cells' => array_map(
                            static fn(array $lines): array => array_slice($lines, $lineOffset, $segmentLines),
                            $wrappedCells
                        ),
                        'line_count' => $segmentLines,
                        'height' => $rowHeight,
                    ];
                    $page['used_height'] += $rowHeight;
                }
            }
            if ($page['rows'] || !$rows) {
                $pages[] = $page;
            }
        }
        if (!$pages) {
            $pages[] = ['group' => ['No records'], 'group_index' => 0, 'rows' => [], 'used_height' => 0];
        }

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
        ];
        $pageReferences = [];
        foreach ($pages as $pageIndex => $pageLines) {
            $pageObjectId = 5 + ($pageIndex * 2);
            $contentObjectId = $pageObjectId + 1;
            $pageReferences[] = $pageObjectId . ' 0 R';

            $stream = "0.12 0.18 0.32 rg\n";
            $stream .= self::drawPdfText($title, 28, 565, 14, true);
            $stream .= self::drawPdfText(
                'Generated: ' . date('Y-m-d H:i:s') . '    Records: ' . count($rows),
                28,
                547,
                8,
                false
            );
            $stream .= self::drawPdfText('Page ' . ($pageIndex + 1) . ' of ' . count($pages), 770, 547, 8, false);
            if (count($columnGroups) > 1) {
                $firstColumn = ($pageLines['group_index'] * 6) + 1;
                $lastColumn = $firstColumn + count($pageLines['group']) - 1;
                $stream .= self::drawPdfText(
                    'Columns ' . $firstColumn . '-' . $lastColumn . ' of ' . count($columns),
                    28,
                    535,
                    7,
                    false
                );
            }

            $group = $pageLines['group'];
            $columnCount = count($group) + 1;
            $columnWidth = (842 - 56) / $columnCount;
            $xPositions = [];
            for ($columnIndex = 0; $columnIndex < $columnCount; $columnIndex++) {
                $xPositions[] = 28 + ($columnIndex * $columnWidth);
            }
            $y = $tableTop;
            $stream .= self::drawPdfCell($xPositions[0], $y, $columnWidth, $headerHeight, '#', true, true);
            foreach ($group as $columnIndex => $column) {
                $stream .= self::drawPdfCell(
                    $xPositions[$columnIndex + 1],
                    $y,
                    $columnWidth,
                    $headerHeight,
                    self::humanizeColumn((string)$column),
                    true,
                    true
                );
            }
            $y -= $headerHeight;

            if (!$pageLines['rows']) {
                $stream .= self::drawPdfText('No records found for this report scope.', 34, $y - 18, 8, false);
            }

            foreach ($pageLines['rows'] as $tableRowIndex => $tableRow) {
                $rowTop = $y;
                $rowHeight = $tableRow['height'];
                $stream .= self::drawPdfCell(
                    $xPositions[0],
                    $rowTop,
                    $columnWidth,
                    $rowHeight,
                    (string)$tableRow['index'],
                    false,
                    $tableRowIndex % 2 === 1
                );
                foreach ($group as $columnIndex => $_column) {
                    $cellLines = $tableRow['cells'][$columnIndex] ?? [''];
                    $stream .= self::drawPdfCell(
                        $xPositions[$columnIndex + 1],
                        $rowTop,
                        $columnWidth,
                        $rowHeight,
                        $cellLines,
                        false,
                        $tableRowIndex % 2 === 1
                    );
                }
                $y -= $rowHeight;
            }
            $stream .= "0.45 0.50 0.60 RG 0.5 w 28 24 m 814 24 l S\n";
            $stream .= self::drawPdfText('WhatsApp Report', 28, 13, 7, false);
            $streamLength = strlen($stream);
            $objects[$pageObjectId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $contentObjectId . ' 0 R >>';
            $objects[$contentObjectId] = "<< /Length {$streamLength} >>\nstream\n{$stream}endstream";
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $pageReferences) . '] /Count ' . count($pages) . ' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $objectId => $objectBody) {
            $offsets[$objectId] = strlen($pdf);
            $pdf .= "{$objectId} 0 obj\n{$objectBody}\nendobj\n";
        }
        $xrefOffset = strlen($pdf);
        $objectCount = max(array_keys($objects)) + 1;
        $pdf .= "xref\n0 {$objectCount}\n0000000000 65535 f \n";
        for ($objectId = 1; $objectId < $objectCount; $objectId++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$objectId] ?? 0);
        }
        $pdf .= "trailer\n<< /Size {$objectCount} /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private static function wrapPdfText(string $text, int $maxChars): array {
        $text = trim(str_replace(["\r\n", "\r", "\n"], ' ', $text));
        if ($text === '') {
            return [''];
        }
        return explode("\n", wordwrap($text, $maxChars, "\n", true));
    }

    private static function drawPdfCell(float $x, float $top, float $width, float $height, $text, bool $header, bool $alternate): string {
        if ($header) {
            $fill = '0.12 0.18 0.32';
        } elseif ($alternate) {
            $fill = '0.94 0.96 0.98';
        } else {
            $fill = '1 1 1';
        }
        $color = array_map('floatval', explode(' ', $fill));
        $stream = sprintf(
            "%.2f %.2f %.2f rg\n%.2f %.2f %.2f %.2f re f\n",
            $color[0],
            $color[1],
            $color[2],
            $x,
            $top - $height,
            $width,
            $height
        );
        $stream .= "0.78 0.81 0.86 RG 0.4 w\n";
        $stream .= sprintf("%.2f %.2f %.2f %.2f re S\n", $x, $top - $height, $width, $height);
        $stream .= $header ? "1 1 1 rg\n" : "0.12 0.18 0.32 rg\n";
        $lines = is_array($text) ? $text : self::wrapPdfText(self::pdfText((string)$text), max(8, (int)floor(($width - 10) / 3.8)));
        $fontSize = $header ? 7.2 : 7;
        $baseline = $top - 11;
        foreach ($lines as $lineIndex => $line) {
            $stream .= self::drawPdfText((string)$line, $x + 5, $baseline - ($lineIndex * 9), $fontSize, $header);
        }
        return $stream;
    }

    private static function drawPdfText(string $text, float $x, float $y, float $fontSize, bool $bold): string {
        $font = $bold ? 'F2' : 'F1';
        return sprintf(
            "BT /%s %.1f Tf %.2f %.2f Td %s ET\n",
            $font,
            $fontSize,
            $x,
            $y,
            self::pdfLiteral(self::pdfText($text))
        );
    }

    private static function csvSafeValue($value) {
        if (!is_string($value)) {
            return $value;
        }
        if (preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            return $value;
        }
        return preg_match('/^[\s]*[=+\-@]/u', $value) ? "'" . $value : $value;
    }

    private static function valueToString($value): string {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_scalar($value) || $value === null) {
            return (string)($value ?? '');
        }
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    private static function pdfText(string $value): string {
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        return $text === false ? preg_replace('/[^\x20-\x7E]/', '?', $value) : $text;
    }

    private static function pdfLiteral(string $value): string {
        return '(' . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value) . ') Tj';
    }

    private static function humanizeColumn(string $column): string {
        return ucwords(str_replace(['_', '-'], ' ', $column));
    }

    private static function safeFilename(string $filename, string $extension): string {
        $safe = preg_replace('/[^A-Za-z0-9_-]+/', '_', $filename);
        return trim((string)$safe, '_') . '.' . $extension;
    }
}
