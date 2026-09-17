<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use RuntimeException;
use ZipArchive;

class ReportDocumentService
{
    private const WORD_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const XML_NS = 'http://www.w3.org/XML/1998/namespace';

    private const TABLE_WIDTH = 9360;

    public function create(array $report, string $filterSummary): string
    {
        $template = resource_path('templates/nstp-report-template.docx');

        if (! is_file($template)) {
            throw new RuntimeException('The NSTP report document template is unavailable.');
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'smart-nstp-report-');

        if ($temporaryPath === false || ! copy($template, $temporaryPath)) {
            throw new RuntimeException('The NSTP report document could not be prepared.');
        }

        $archive = new ZipArchive;

        try {
            if ($archive->open($temporaryPath) !== true) {
                throw new RuntimeException('The NSTP report document template could not be opened.');
            }

            $documentXml = $archive->getFromName('word/document.xml');

            if ($documentXml === false) {
                throw new RuntimeException('The NSTP report document template is incomplete.');
            }

            $document = new DOMDocument('1.0', 'UTF-8');
            $document->preserveWhiteSpace = false;
            $document->formatOutput = false;

            if (! $document->loadXML($documentXml, LIBXML_NONET)) {
                throw new RuntimeException('The NSTP report document template contains invalid XML.');
            }

            $body = $document->getElementsByTagNameNS(self::WORD_NS, 'body')->item(0);

            if (! $body instanceof DOMElement) {
                throw new RuntimeException('The NSTP report document body is missing.');
            }

            $sectionProperties = $this->findDirectChild($body, 'sectPr');

            if (! $sectionProperties instanceof DOMElement) {
                throw new RuntimeException('The NSTP report document section settings are missing.');
            }

            foreach (iterator_to_array($body->childNodes) as $child) {
                if ($child !== $sectionProperties) {
                    $body->removeChild($child);
                }
            }

            $this->setPageMargins($document, $sectionProperties);
            $this->appendReportContent($document, $body, $sectionProperties, $report, $filterSummary);

            $updatedXml = $document->saveXML();

            if ($updatedXml === false || ! $archive->addFromString('word/document.xml', $updatedXml)) {
                throw new RuntimeException('The NSTP report document content could not be written.');
            }

            $archive->close();

            return $temporaryPath;
        } catch (\Throwable $exception) {
            $archive->close();
            @unlink($temporaryPath);

            throw $exception;
        }
    }

    private function appendReportContent(
        DOMDocument $document,
        DOMElement $body,
        DOMElement $sectionProperties,
        array $report,
        string $filterSummary,
    ): void {
        $body->insertBefore($this->paragraph($document, (string) $report['title'], [
            'alignment' => 'center',
            'bold' => true,
            'color' => '008000',
            'size' => 30,
            'space_after' => 100,
            'keep_next' => true,
        ]), $sectionProperties);

        $body->insertBefore($this->paragraph($document, $filterSummary, [
            'alignment' => 'center',
            'size' => 18,
            'space_after' => 60,
            'keep_next' => true,
        ]), $sectionProperties);

        $recordCount = $report['rows']->count();
        $generatedAt = $report['generated_at']->format('F j, Y g:i A');
        $recordLabel = $recordCount === 1 ? 'record' : 'records';
        $body->insertBefore($this->paragraph(
            $document,
            "Generated {$generatedAt} | {$recordCount} {$recordLabel}",
            [
                'alignment' => 'center',
                'italic' => true,
                'color' => '5F6B7A',
                'size' => 16,
                'space_after' => 180,
                'keep_next' => true,
            ],
        ), $sectionProperties);

        if (array_key_exists('groups', $report) && $report['groups']->isNotEmpty()) {
            foreach ($report['groups'] as $index => $group) {
                $body->insertBefore($this->paragraph($document, (string) $group['title'], [
                    'bold' => true,
                    'color' => '008000',
                    'size' => 22,
                    'space_before' => $index === 0 ? 0 : 180,
                    'space_after' => 30,
                    'keep_next' => true,
                ]), $sectionProperties);
                $body->insertBefore($this->paragraph($document, (string) $group['subtitle'], [
                    'italic' => true,
                    'color' => '5F6B7A',
                    'size' => 16,
                    'space_after' => 80,
                    'keep_next' => true,
                ]), $sectionProperties);
                $body->insertBefore($this->table(
                    $document,
                    $report['headers'],
                    $group['rows']->all(),
                ), $sectionProperties);
            }

            return;
        }

        $body->insertBefore($this->table($document, $report['headers'], $report['rows']->all()), $sectionProperties);
    }

    private function paragraph(DOMDocument $document, string $text, array $options = []): DOMElement
    {
        $paragraph = $this->wordElement($document, 'p');
        $properties = $this->wordElement($document, 'pPr');
        $paragraph->appendChild($properties);

        if (isset($options['alignment'])) {
            $alignment = $this->wordElement($document, 'jc');
            $this->wordAttribute($alignment, 'val', (string) $options['alignment']);
            $properties->appendChild($alignment);
        }

        $spacing = $this->wordElement($document, 'spacing');
        $this->wordAttribute($spacing, 'before', (string) ($options['space_before'] ?? 0));
        $this->wordAttribute($spacing, 'after', (string) ($options['space_after'] ?? 0));
        $this->wordAttribute($spacing, 'line', '240');
        $this->wordAttribute($spacing, 'lineRule', 'auto');
        $properties->appendChild($spacing);

        if ($options['keep_next'] ?? false) {
            $properties->appendChild($this->wordElement($document, 'keepNext'));
        }

        $run = $this->wordElement($document, 'r');
        $run->appendChild($this->runProperties($document, $options));
        $textElement = $this->wordElement($document, 't', $text);
        $textElement->setAttributeNS(self::XML_NS, 'xml:space', 'preserve');
        $run->appendChild($textElement);
        $paragraph->appendChild($run);

        return $paragraph;
    }

    private function table(DOMDocument $document, array $headers, array $rows): DOMElement
    {
        $widths = $this->columnWidths($headers, $rows);
        $table = $this->wordElement($document, 'tbl');
        $properties = $this->wordElement($document, 'tblPr');
        $table->appendChild($properties);

        $tableWidth = $this->wordElement($document, 'tblW');
        $this->wordAttribute($tableWidth, 'w', (string) self::TABLE_WIDTH);
        $this->wordAttribute($tableWidth, 'type', 'dxa');
        $properties->appendChild($tableWidth);

        $alignment = $this->wordElement($document, 'jc');
        $this->wordAttribute($alignment, 'val', 'center');
        $properties->appendChild($alignment);

        $layout = $this->wordElement($document, 'tblLayout');
        $this->wordAttribute($layout, 'type', 'fixed');
        $properties->appendChild($layout);
        $properties->appendChild($this->tableBorders($document));
        $properties->appendChild($this->tableCellMargins($document));

        $grid = $this->wordElement($document, 'tblGrid');
        foreach ($widths as $width) {
            $column = $this->wordElement($document, 'gridCol');
            $this->wordAttribute($column, 'w', (string) $width);
            $grid->appendChild($column);
        }
        $table->appendChild($grid);

        $headerRow = $this->wordElement($document, 'tr');
        $headerProperties = $this->wordElement($document, 'trPr');
        $headerProperties->appendChild($this->wordElement($document, 'tblHeader'));
        $headerRow->appendChild($headerProperties);
        foreach (array_values($headers) as $index => $header) {
            $headerRow->appendChild($this->tableCell(
                $document,
                (string) $header,
                $widths[$index],
                true,
                false,
                'center',
            ));
        }
        $table->appendChild($headerRow);

        if ($rows === []) {
            $row = $this->wordElement($document, 'tr');
            $row->appendChild($this->tableCell(
                $document,
                'No records matched the selected filters.',
                self::TABLE_WIDTH,
                false,
                false,
                'center',
                count($headers),
            ));
            $table->appendChild($row);

            return $table;
        }

        foreach ($rows as $rowIndex => $values) {
            $row = $this->wordElement($document, 'tr');
            foreach (array_values($values) as $columnIndex => $value) {
                $row->appendChild($this->tableCell(
                    $document,
                    $this->stringValue($value),
                    $widths[$columnIndex],
                    false,
                    $rowIndex % 2 === 1,
                    $this->cellAlignment((string) $headers[$columnIndex]),
                ));
            }
            $table->appendChild($row);
        }

        return $table;
    }

    private function tableCell(
        DOMDocument $document,
        string $text,
        int $width,
        bool $header,
        bool $alternate,
        string $alignment,
        ?int $columnSpan = null,
    ): DOMElement {
        $cell = $this->wordElement($document, 'tc');
        $properties = $this->wordElement($document, 'tcPr');
        $cell->appendChild($properties);

        $cellWidth = $this->wordElement($document, 'tcW');
        $this->wordAttribute($cellWidth, 'w', (string) $width);
        $this->wordAttribute($cellWidth, 'type', 'dxa');
        $properties->appendChild($cellWidth);

        if ($columnSpan !== null && $columnSpan > 1) {
            $span = $this->wordElement($document, 'gridSpan');
            $this->wordAttribute($span, 'val', (string) $columnSpan);
            $properties->appendChild($span);
        }

        $shading = $this->wordElement($document, 'shd');
        $this->wordAttribute($shading, 'val', 'clear');
        $this->wordAttribute($shading, 'color', 'auto');
        $this->wordAttribute($shading, 'fill', $header ? '008000' : ($alternate ? 'F1F8F1' : 'FFFFFF'));
        $properties->appendChild($shading);

        $verticalAlignment = $this->wordElement($document, 'vAlign');
        $this->wordAttribute($verticalAlignment, 'val', 'center');
        $properties->appendChild($verticalAlignment);

        $cell->appendChild($this->paragraph($document, $text, [
            'alignment' => $alignment,
            'bold' => $header,
            'color' => $header ? 'FFFFFF' : '000000',
            'size' => mb_strlen($text) > 100 ? 14 : 16,
            'space_after' => 0,
        ]));

        return $cell;
    }

    private function runProperties(DOMDocument $document, array $options): DOMElement
    {
        $properties = $this->wordElement($document, 'rPr');
        $fonts = $this->wordElement($document, 'rFonts');
        foreach (['ascii', 'hAnsi', 'cs'] as $attribute) {
            $this->wordAttribute($fonts, $attribute, 'Times New Roman');
        }
        $properties->appendChild($fonts);

        if ($options['bold'] ?? false) {
            $properties->appendChild($this->wordElement($document, 'b'));
        }
        if ($options['italic'] ?? false) {
            $properties->appendChild($this->wordElement($document, 'i'));
        }
        if (isset($options['color'])) {
            $color = $this->wordElement($document, 'color');
            $this->wordAttribute($color, 'val', (string) $options['color']);
            $properties->appendChild($color);
        }

        $sizeValue = (string) ($options['size'] ?? 18);
        foreach (['sz', 'szCs'] as $name) {
            $size = $this->wordElement($document, $name);
            $this->wordAttribute($size, 'val', $sizeValue);
            $properties->appendChild($size);
        }

        return $properties;
    }

    private function tableBorders(DOMDocument $document): DOMElement
    {
        $borders = $this->wordElement($document, 'tblBorders');
        foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $edge) {
            $border = $this->wordElement($document, $edge);
            $this->wordAttribute($border, 'val', 'single');
            $this->wordAttribute($border, 'sz', '4');
            $this->wordAttribute($border, 'space', '0');
            $this->wordAttribute($border, 'color', 'D9D9D9');
            $borders->appendChild($border);
        }

        return $borders;
    }

    private function tableCellMargins(DOMDocument $document): DOMElement
    {
        $margins = $this->wordElement($document, 'tblCellMar');
        foreach (['top' => 75, 'left' => 90, 'bottom' => 75, 'right' => 90] as $edge => $value) {
            $margin = $this->wordElement($document, $edge);
            $this->wordAttribute($margin, 'w', (string) $value);
            $this->wordAttribute($margin, 'type', 'dxa');
            $margins->appendChild($margin);
        }

        return $margins;
    }

    private function columnWidths(array $headers, array $rows): array
    {
        $weights = [];
        foreach (array_values($headers) as $index => $header) {
            $longest = mb_strlen((string) $header);
            foreach (array_slice($rows, 0, 100) as $row) {
                $longest = max($longest, mb_strlen($this->stringValue(array_values($row)[$index] ?? '')));
            }
            $weights[] = max(8, min(28, $longest));
        }

        $count = max(1, count($weights));
        $minimum = min(720, intdiv(self::TABLE_WIDTH, $count));
        $remaining = self::TABLE_WIDTH - ($minimum * $count);
        $weightTotal = max(1, array_sum($weights));
        $widths = [];
        $used = 0;

        foreach ($weights as $index => $weight) {
            $width = $index === $count - 1
                ? self::TABLE_WIDTH - $used
                : $minimum + (int) floor($remaining * ($weight / $weightTotal));
            $widths[] = $width;
            $used += $width;
        }

        return $widths;
    }

    private function cellAlignment(string $header): string
    {
        return preg_match('/^(Component|Section|Term|Date|Status|Time In|Time Out|Source|Enrollment|Utilization|Attendance Sessions|Assessments|Average Grade|Graded Score Items|Raw Score Rate|Weighted Total|Final Grade)$/i', $header)
            ? 'center'
            : 'left';
    }

    private function setPageMargins(DOMDocument $document, DOMElement $sectionProperties): void
    {
        $margins = $this->findDirectChild($sectionProperties, 'pgMar');

        if (! $margins instanceof DOMElement) {
            $margins = $this->wordElement($document, 'pgMar');
            $sectionProperties->appendChild($margins);
        }

        $this->wordAttribute($margins, 'top', '3024');
        $this->wordAttribute($margins, 'bottom', '2016');
        $this->wordAttribute($margins, 'left', '1273');
        $this->wordAttribute($margins, 'right', '1273');
        $this->wordAttribute($margins, 'header', '340');
        $this->wordAttribute($margins, 'footer', '0');
        $this->wordAttribute($margins, 'gutter', '0');
    }

    private function findDirectChild(DOMElement $parent, string $localName): ?DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->namespaceURI === self::WORD_NS && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    private function wordElement(DOMDocument $document, string $name, ?string $text = null): DOMElement
    {
        return $document->createElementNS(self::WORD_NS, 'w:'.$name, $text);
    }

    private function wordAttribute(DOMElement $element, string $name, string $value): void
    {
        $element->setAttributeNS(self::WORD_NS, 'w:'.$name, $value);
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) || $value === null
            ? (string) $value
            : (string) json_encode($value, JSON_UNESCAPED_UNICODE);
    }
}
