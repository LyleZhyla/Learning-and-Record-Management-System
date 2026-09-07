<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ReportSpreadsheetService
{
    private const HEADER_ROW = 6;

    /**
     * @param  array{title: string, headers: array<int, string>, rows: iterable<int, array<string, mixed>>, generated_at: DateTimeInterface}  $report
     */
    public function create(array $report, string $filterSummary): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator('SNAPIE Smart NSTP')
            ->setTitle($report['title'])
            ->setSubject('NSTP operational report');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($report['title'], 0, 31));
        $sheet->setShowGridlines(false);

        $columnCount = count($report['headers']);
        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);
        $sheet->mergeCells("A2:{$lastColumn}2");
        $sheet->mergeCells("A3:{$lastColumn}3");
        $sheet->mergeCells("A4:{$lastColumn}4");
        $sheet->setCellValue('A2', $report['title']);
        $sheet->setCellValue('A3', 'Generated '.$report['generated_at']->format('F d, Y · h:i A'));
        $sheet->setCellValue('A4', 'Filters: '.$filterSummary);
        $sheet->fromArray([$report['headers']], null, 'A'.self::HEADER_ROW);

        $sheet->getStyle("A2:{$lastColumn}2")->getFont()->setName('Arial')->setBold(true)->setSize(16)->getColor()->setARGB('FF173760');
        $sheet->getStyle("A3:{$lastColumn}4")->getFont()->setName('Arial')->setItalic(true)->setSize(10)->getColor()->setARGB('FF64748B');
        $sheet->getStyle('A'.self::HEADER_ROW.":{$lastColumn}".self::HEADER_ROW)->applyFromArray([
            'font' => ['name' => 'Arial', 'bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF173760']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension(self::HEADER_ROW)->setRowHeight(30);

        $columnWidths = array_map(fn (string $header): int => mb_strlen($header), $report['headers']);
        $rowNumber = self::HEADER_ROW + 1;
        foreach ($report['rows'] as $row) {
            foreach (array_values($row) as $columnOffset => $value) {
                $header = $report['headers'][$columnOffset];
                $this->writeValue($sheet->getCell([$columnOffset + 1, $rowNumber]), $header, $value);
                $columnWidths[$columnOffset] = max($columnWidths[$columnOffset], mb_strlen((string) $value));
            }

            if ($rowNumber % 2 === 0) {
                $sheet->getStyle("A{$rowNumber}:{$lastColumn}{$rowNumber}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF5F8FC');
            }

            $rowNumber++;
        }

        $lastRow = max(self::HEADER_ROW, $rowNumber - 1);
        if ($lastRow > self::HEADER_ROW) {
            $sheet->getStyle('A'.(self::HEADER_ROW + 1).":{$lastColumn}{$lastRow}")->applyFromArray([
                'font' => ['name' => 'Arial', 'size' => 10, 'color' => ['argb' => 'FF17243C']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders' => ['bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['argb' => 'FFDCE3EC']]],
            ]);
        }

        $sheet->freezePane('A'.(self::HEADER_ROW + 1));
        $sheet->setAutoFilter('A'.self::HEADER_ROW.":{$lastColumn}{$lastRow}");
        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.5)->setRight(0.4)->setBottom(0.5)->setLeft(0.4);

        for ($column = 1; $column <= $columnCount; $column++) {
            $letter = Coordinate::stringFromColumnIndex($column);
            $sheet->getColumnDimension($letter)->setWidth(min(40, max(12, $columnWidths[$column - 1] + 2)));
        }

        return $spreadsheet;
    }

    private function writeValue($cell, string $header, mixed $value): void
    {
        if ($value instanceof DateTimeInterface) {
            $cell->setValue(Date::dateTimeToExcel($value));
            $cell->getStyle()->getNumberFormat()->setFormatCode('mmm d, yyyy');

            return;
        }

        if (is_int($value) || is_float($value)) {
            $cell->setValue($value);

            return;
        }

        $text = (string) $value;

        if (in_array($header, ['Raw Score Rate', 'Weighted Total', 'Utilization'], true)
            && preg_match('/^-?\d+(?:\.\d+)?%$/', $text)) {
            $cell->setValue(((float) rtrim($text, '%')) / 100);
            $cell->getStyle()->getNumberFormat()->setFormatCode($header === 'Utilization' ? '0.0%' : '0.00%');

            return;
        }

        if (in_array($header, ['Final Grade', 'Average Grade'], true) && is_numeric($text)) {
            $cell->setValue((float) $text);
            $cell->getStyle()->getNumberFormat()->setFormatCode('0.00');

            return;
        }

        if ($header === 'Date') {
            $date = DateTimeImmutable::createFromFormat('M d, Y', $text);
            if ($date !== false) {
                $cell->setValue(Date::dateTimeToExcel($date));
                $cell->getStyle()->getNumberFormat()->setFormatCode('mmm d, yyyy');

                return;
            }
        }

        $cell->setValueExplicit($text, DataType::TYPE_STRING);
    }
}
