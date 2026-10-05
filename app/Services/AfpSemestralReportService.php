<?php

namespace App\Services;

use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class AfpSemestralReportService
{
    public const HEADERS = ['Student', 'MS Level', 'Sex', 'Course', 'Weighted Total', 'Final Grade', 'Remarks'];

    public function __construct(private GradeService $grades) {}

    public function report(array $filters): array
    {
        $rows = $this->cadets($filters)->map(function (array $record): array {
            $profile = $record['enrollment']->student->studentProfile;

            return [
                'student' => $this->studentName($record['enrollment']),
                'ms_level' => $record['enrollment']->rotc_category ?: 'Unassigned',
                'sex' => $this->sexLabel($profile?->sex),
                'course' => $this->course($record['enrollment']),
                'weighted_total' => $record['summary']['percentage'] === null ? '—' : number_format($record['summary']['percentage'], 2).'%',
                'final_grade' => $record['summary']['grade'] === null ? '—' : number_format($record['summary']['grade'], 2),
                'remarks' => $this->remarks($record['summary']),
            ];
        })->values();

        return [
            'title' => 'AFP Semestral Report - ROTC Grades',
            'headers' => self::HEADERS,
            'rows' => $rows,
            'generated_at' => now(),
        ];
    }

    public function createWorkbook(array $filters): Spreadsheet
    {
        $template = resource_path('templates/afp-semestral-report-rotc.xlsx');
        if (! is_file($template)) {
            throw new RuntimeException('The AFP ROTC semestral report template is unavailable.');
        }

        $spreadsheet = $this->loadTemplate($template);
        $cog = $spreadsheet->getSheetByName('COG');
        $rog = $spreadsheet->getSheetByName('ROG');
        if (! $cog || ! $rog) {
            throw new RuntimeException('The AFP ROTC template is missing a required worksheet.');
        }

        $records = $this->cadets($filters);
        $groups = $this->groups($records);
        $this->writeTermHeaders($cog, $rog, $filters);
        $this->writeCog($cog, $groups);
        $this->writeRog($rog, $groups);

        $spreadsheet->getProperties()
            ->setCreator('SNAPIE Smart NSTP')
            ->setTitle('AFP Semestral Report - ROTC Grades')
            ->setSubject('ROTC semestral grades including passed, failed, incomplete, and ungraded cadets');
        $spreadsheet->setActiveSheetIndexByName('COG');

        return $spreadsheet;
    }

    /** @return Collection<int, array{enrollment: NstpEnrollment, summary: array}> */
    private function cadets(array $filters): Collection
    {
        return NstpEnrollment::query()
            ->with(['student.studentProfile', 'component', 'section'])
            ->where('status', 'enrolled')
            ->whereNotNull('section_id')
            ->whereHas('component', fn ($query) => $query->where('code', 'ROTC'))
            ->whereHas('section', function ($query) use ($filters): void {
                $query->when($filters['academic_year'] ?? null, fn ($q, $value) => $q->where('academic_year', $value))
                    ->when($filters['semester'] ?? null, fn ($q, $value) => $q->where('semester', $value))
                    ->when($filters['component_id'] ?? null, fn ($q, $value) => $q->where('component_id', $value))
                    ->when($filters['section_id'] ?? null, fn ($q, $value) => $q->where('id', $value))
                    ->when($filters['facilitator_id'] ?? null, fn ($q, $value) => $q->where('facilitator_id', $value));
            })
            ->get()
            ->map(fn (NstpEnrollment $enrollment): array => [
                'enrollment' => $enrollment,
                'summary' => $this->grades->summary($enrollment->student, $enrollment->section_id),
            ])
            ->sortBy(function (array $record): string {
                $enrollment = $record['enrollment'];
                $profile = $enrollment->student->studentProfile;

                return implode('|', [
                    $this->categoryOrder($enrollment->rotc_category),
                    $this->sexOrder($profile?->sex),
                    $profile?->last_name ?? $enrollment->student->name,
                    $profile?->first_name ?? '',
                ]);
            }, SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /** @return Collection<string, Collection<int, array{enrollment: NstpEnrollment, summary: array}>> */
    private function groups(Collection $records): Collection
    {
        return $records->groupBy(function (array $record): string {
            $enrollment = $record['enrollment'];
            $category = $enrollment->rotc_category ?: 'UNASSIGNED MS LEVEL';
            $sex = strtoupper($this->sexLabel($enrollment->student->studentProfile?->sex));

            return trim($category.' '.$sex.' CADETS');
        });
    }

    private function writeTermHeaders(Worksheet $cog, Worksheet $rog, array $filters): void
    {
        $semester = NstpSection::SEMESTERS[$filters['semester'] ?? ''] ?? str($filters['semester'] ?? '')->headline();
        $academicYear = $filters['academic_year'] ?? 'Not specified';
        $label = '('.$semester.', SY '.$academicYear.')';
        $cog->setCellValue('A8', $label);
        $rog->setCellValue('A9', $label);
        $excelDate = Date::dateTimeToExcel(now());
        $cog->setCellValue('M6', $excelDate);
        $rog->setCellValue('E6', $excelDate);
        $cog->getStyle('M6')->getNumberFormat()->setFormatCode('dd-mmm-yy');
        $rog->getStyle('E6')->getNumberFormat()->setFormatCode('dd-mmm-yy');
    }

    private function writeCog(Worksheet $sheet, Collection $groups): void
    {
        $headingStyles = $this->rowStyles($sheet, 10, 14);
        $headerStyles = $this->rowStyles($sheet, 11, 14);
        $dataStyles = $this->rowStyles($sheet, 12, 14);
        $headingHeight = $sheet->getRowDimension(10)->getRowHeight();
        $headerHeight = $sheet->getRowDimension(11)->getRowHeight();
        $dataHeight = $sheet->getRowDimension(12)->getRowHeight();
        $this->clearBody($sheet, 10, 14);

        $row = 10;
        foreach ($groups as $heading => $records) {
            $this->applyRowStyles($sheet, $row, $headingStyles, $headingHeight);
            $sheet->mergeCells("A{$row}:N{$row}");
            $sheet->setCellValue("A{$row}", $heading);
            $row++;

            $this->applyRowStyles($sheet, $row, $headerStyles, $headerHeight);
            $sheet->fromArray([['NR', 'NAME', 'COURSE', 'ATTENDANCE', '30%', 'ATTITUDE (100)', '30%', 'MIDTERM EXAM', '20%', 'FINAL EXAM', '20%', 'TOTAL (GRADE IN %)', 'Equivalent', 'REMARKS']], null, "A{$row}");
            $row++;

            foreach ($records->values() as $index => $record) {
                $this->applyRowStyles($sheet, $row, $dataStyles, $dataHeight);
                $summary = $record['summary'];
                $sheet->fromArray([[
                    $index + 1,
                    $this->studentName($record['enrollment']),
                    $this->course($record['enrollment']),
                    null, null, null, null, null, null, null, null,
                    $summary['percentage'],
                    $summary['grade'],
                    $this->remarks($summary),
                ]], null, "A{$row}", true);
                $row++;
            }
            $row++;
        }

        if ($groups->isEmpty()) {
            $this->applyRowStyles($sheet, $row, $headingStyles, $headingHeight);
            $sheet->mergeCells("A{$row}:N{$row}");
            $sheet->setCellValue("A{$row}", 'NO ROTC CADETS MATCHED THE SELECTED TERM');
        }

        $lastRow = max(10, $row - 1);
        if ($groups->isNotEmpty()) {
            $sheet->getStyle("L12:L{$lastRow}")->getNumberFormat()->setFormatCode('0.00');
            $sheet->getStyle("M12:M{$lastRow}")->getNumberFormat()->setFormatCode('0.00');
        }
        $sheet->getPageSetup()->setPrintArea("A1:N{$lastRow}");
    }

    private function writeRog(Worksheet $sheet, Collection $groups): void
    {
        $headingStyles = $this->rowStyles($sheet, 11, 6);
        $headerStyles = $this->rowStyles($sheet, 12, 6);
        $dataStyles = $this->rowStyles($sheet, 13, 6);
        $headingHeight = $sheet->getRowDimension(11)->getRowHeight();
        $headerHeight = $sheet->getRowDimension(12)->getRowHeight();
        $dataHeight = $sheet->getRowDimension(13)->getRowHeight();
        $this->clearBody($sheet, 11, 6);

        $row = 11;
        foreach ($groups as $heading => $records) {
            $this->applyRowStyles($sheet, $row, $headingStyles, $headingHeight);
            $sheet->mergeCells("A{$row}:F{$row}");
            $sheet->setCellValue("A{$row}", $heading);
            $row++;

            $this->applyRowStyles($sheet, $row, $headerStyles, $headerHeight);
            $sheet->fromArray([['NR', 'NAME', 'COURSE', 'TOTAL (GRADE IN %)', 'Equivalent', 'REMARKS']], null, "A{$row}");
            $row++;

            foreach ($records->values() as $index => $record) {
                $this->applyRowStyles($sheet, $row, $dataStyles, $dataHeight);
                $summary = $record['summary'];
                $sheet->fromArray([[
                    $index + 1,
                    $this->studentName($record['enrollment']),
                    $this->course($record['enrollment']),
                    $summary['percentage'],
                    $summary['grade'],
                    $this->remarks($summary),
                ]], null, "A{$row}", true);
                $row++;
            }
            $row++;
        }

        if ($groups->isEmpty()) {
            $this->applyRowStyles($sheet, $row, $headingStyles, $headingHeight);
            $sheet->mergeCells("A{$row}:F{$row}");
            $sheet->setCellValue("A{$row}", 'NO ROTC CADETS MATCHED THE SELECTED TERM');
        }

        $lastRow = max(11, $row - 1);
        if ($groups->isNotEmpty()) {
            $sheet->getStyle("D13:D{$lastRow}")->getNumberFormat()->setFormatCode('0.00');
            $sheet->getStyle("E13:E{$lastRow}")->getNumberFormat()->setFormatCode('0.00');
        }
        $sheet->getPageSetup()->setPrintArea("A1:F{$lastRow}");
    }

    private function clearBody(Worksheet $sheet, int $firstRow, int $columnCount): void
    {
        foreach ($sheet->getMergeCells() as $range) {
            [, $startRow] = Coordinate::coordinateFromString(explode(':', $range)[0]);
            if ($startRow >= $firstRow) {
                $sheet->unmergeCells($range);
            }
        }

        $lastRow = $sheet->getHighestRow();
        for ($row = $firstRow; $row <= $lastRow; $row++) {
            for ($column = 1; $column <= $columnCount; $column++) {
                $sheet->setCellValue([$column, $row], null);
            }
        }
    }

    /** @return array<int, \PhpOffice\PhpSpreadsheet\Style\Style> */
    private function rowStyles(Worksheet $sheet, int $row, int $columnCount): array
    {
        $styles = [];
        for ($column = 1; $column <= $columnCount; $column++) {
            $styles[$column] = clone $sheet->getStyle([$column, $row]);
        }

        return $styles;
    }

    /** @param array<int, \PhpOffice\PhpSpreadsheet\Style\Style> $styles */
    private function applyRowStyles(Worksheet $sheet, int $row, array $styles, float $height): void
    {
        foreach ($styles as $column => $style) {
            $sheet->duplicateStyle($style, Coordinate::stringFromColumnIndex($column).$row);
        }
        $sheet->getRowDimension($row)->setRowHeight($height);
    }

    private function remarks(array $summary): string
    {
        if ($summary['total_count'] === 0 || $summary['graded_count'] === 0) {
            return 'NO GRADES';
        }
        if ($summary['graded_count'] < $summary['total_count']) {
            return 'IN PROGRESS';
        }

        return $summary['percentage'] >= (float) $summary['settings']->passing_percentage ? 'PASSED' : 'FAILED';
    }

    private function studentName(NstpEnrollment $enrollment): string
    {
        $profile = $enrollment->student->studentProfile;
        if (! $profile) {
            return mb_strtoupper($enrollment->student->name);
        }

        $middleInitial = filled($profile->middle_name) ? ' '.mb_strtoupper(mb_substr($profile->middle_name, 0, 1)).'.' : '';
        $extension = filled($profile->extension_name) ? ' '.mb_strtoupper($profile->extension_name) : '';

        return mb_strtoupper($profile->last_name.', '.$profile->first_name.$middleInitial.$extension);
    }

    private function course(NstpEnrollment $enrollment): string
    {
        $profile = $enrollment->student->studentProfile;

        return trim(($profile?->course ?? '').(filled($profile?->year_section) ? ' '.$profile->year_section : ''));
    }

    private function sexLabel(?string $sex): string
    {
        return match (strtolower(trim((string) $sex))) {
            'male', 'm' => 'Male',
            'female', 'f' => 'Female',
            default => 'Unspecified',
        };
    }

    private function categoryOrder(?string $category): string
    {
        return match ($category) {
            'MS-1' => '1',
            'MS-31' => '2',
            'MS-41' => '3',
            default => '9',
        };
    }

    private function sexOrder(?string $sex): string
    {
        return match (strtolower(trim((string) $sex))) {
            'male', 'm' => '1',
            'female', 'f' => '2',
            default => '9',
        };
    }

    private function loadTemplate(string $path): Spreadsheet
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'afp-report-template-');
        if ($temporaryPath === false || file_put_contents($temporaryPath, file_get_contents($path)) === false) {
            throw new RuntimeException('The AFP ROTC semestral report template could not be prepared.');
        }

        register_shutdown_function(static fn () => @unlink($temporaryPath));

        return IOFactory::load($temporaryPath);
    }
}
