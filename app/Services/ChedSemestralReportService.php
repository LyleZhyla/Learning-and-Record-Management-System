<?php

namespace App\Services;

use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class ChedSemestralReportService
{
    public const HEADERS = [
        'Seq. No.',
        'NSTP Graduation Year',
        'NSTP Component',
        'Region',
        'NSTP Serial Number',
        'Last Name',
        'First Name',
        'Extension Name',
        'Middle Name',
        'Birthdate',
        'Sex',
        'Street / Barangay',
        'Town / City / Municipality',
        'Province',
        'HEI Name',
        'Type of HEI',
        'Program / Course',
        'Year Level',
        'Email Address',
        'Contact Number',
    ];

    public function __construct(private GradeService $grades) {}

    public function report(array $filters): array
    {
        $rows = $this->passers($filters)->values()->map(fn (NstpEnrollment $enrollment, int $index): array => $this->reportRow($enrollment, $index + 1));

        return [
            'title' => 'CHED Semestral Report - CWTS and LTS Passers',
            'headers' => self::HEADERS,
            'rows' => $rows,
            'generated_at' => now(),
        ];
    }

    public function createWorkbook(array $filters): Spreadsheet
    {
        $template = resource_path('templates/ched-semestral-report-cwts-lts.xlsx');
        if (! is_file($template)) {
            throw new RuntimeException('The CHED semestral report template is unavailable.');
        }

        $spreadsheet = $this->loadTemplate($template);
        $passers = $this->passers($filters)->values();
        $detailRows = $passers->map(fn (NstpEnrollment $enrollment, int $index): array => $this->spreadsheetRow($enrollment, $index + 1))->all();

        $this->writeSummary($spreadsheet, $passers, $filters);
        $this->writeDetailSheet($spreadsheet->getSheetByName('NSTP Enrollment List'), $detailRows);
        $this->writeDetailSheet($spreadsheet->getSheetByName('NSTP Graduates'), $detailRows);

        $spreadsheet->getProperties()
            ->setCreator('SNAPIE Smart NSTP')
            ->setTitle('CHED Semestral Report - CWTS and LTS Passers')
            ->setSubject('CWTS and LTS passers for NSTP serial number processing');
        $spreadsheet->setActiveSheetIndexByName('Summary');

        return $spreadsheet;
    }

    /** @return Collection<int, NstpEnrollment> */
    private function passers(array $filters): Collection
    {
        return NstpEnrollment::query()
            ->with(['student.studentProfile', 'component', 'section'])
            ->where('status', 'enrolled')
            ->whereNotNull('section_id')
            ->whereHas('component', fn ($query) => $query->whereIn('code', ['CWTS', 'LTS']))
            ->whereHas('section', function ($query) use ($filters): void {
                $query->when($filters['academic_year'] ?? null, fn ($q, $value) => $q->where('academic_year', $value))
                    ->when($filters['semester'] ?? null, fn ($q, $value) => $q->where('semester', $value))
                    ->when($filters['component_id'] ?? null, fn ($q, $value) => $q->where('component_id', $value))
                    ->when($filters['section_id'] ?? null, fn ($q, $value) => $q->where('id', $value))
                    ->when($filters['facilitator_id'] ?? null, fn ($q, $value) => $q->where('facilitator_id', $value));
            })
            ->get()
            ->filter(function (NstpEnrollment $enrollment): bool {
                $summary = $this->grades->summary($enrollment->student, $enrollment->section_id);

                return $summary['total_count'] > 0
                    && $summary['graded_count'] === $summary['total_count']
                    && $summary['percentage'] !== null
                    && $summary['percentage'] >= (float) $summary['settings']->passing_percentage;
            })
            ->sortBy(function (NstpEnrollment $enrollment): string {
                $profile = $enrollment->student->studentProfile;

                return implode('|', [
                    $enrollment->component->code,
                    $profile?->last_name ?? $enrollment->student->name,
                    $profile?->first_name ?? '',
                    $profile?->middle_name ?? '',
                ]);
            }, SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    private function reportRow(NstpEnrollment $enrollment, int $sequence): array
    {
        $row = $this->commonRow($enrollment, $sequence);
        $row['birthdate'] = $enrollment->student->studentProfile?->date_of_birth?->format('m/d/Y') ?? '';

        return $row;
    }

    private function spreadsheetRow(NstpEnrollment $enrollment, int $sequence): array
    {
        $row = array_values($this->commonRow($enrollment, $sequence));
        $birthdate = $enrollment->student->studentProfile?->date_of_birth;
        $row[9] = $birthdate ? Date::dateTimeToExcel($birthdate) : null;

        return $row;
    }

    private function commonRow(NstpEnrollment $enrollment, int $sequence): array
    {
        $student = $enrollment->student;
        $profile = $student->studentProfile;

        return [
            'sequence' => $sequence,
            'year' => $enrollment->academic_year,
            'component' => $enrollment->component->code,
            'region' => (string) config('ched.region'),
            'serial_number' => '',
            'last_name' => $profile?->last_name ?? $student->name,
            'first_name' => $profile?->first_name ?? '',
            'extension_name' => $profile?->extension_name ?? '',
            'middle_name' => $profile?->middle_name ?? '',
            'birthdate' => '',
            'sex' => $this->sexCode($profile?->sex),
            'street_barangay' => $profile?->barangay ?? '',
            'city_municipality' => $profile?->city_municipality ?? '',
            'province' => $profile?->province ?? '',
            'hei_name' => (string) config('ched.hei_name'),
            'hei_type' => (string) config('ched.hei_type'),
            'course' => $profile?->course ?? '',
            'year_level' => $this->yearLevel($profile?->year_section),
            'email' => $student->email,
            'contact_number' => $profile?->contact_number ?? '',
        ];
    }

    private function writeSummary(Spreadsheet $spreadsheet, Collection $passers, array $filters): void
    {
        $sheet = $spreadsheet->getSheetByName('Summary');
        if (! $sheet) {
            throw new RuntimeException('The CHED template is missing the Summary worksheet.');
        }

        $academicYear = $filters['academic_year'] ?? $passers->pluck('academic_year')->unique()->implode(', ');
        $semester = isset($filters['semester']) ? (NstpSection::SEMESTERS[$filters['semester']] ?? str($filters['semester'])->headline()) : null;
        $sheet->setCellValue('A2', 'Summary of NSTP enrollment and graduates AY '.($academicYear ?: 'Not specified').($semester ? ' ('.$semester.')' : ''));
        $sheet->getStyle('B6:I8')->getNumberFormat()->setFormatCode('0');
        $sheet->getStyle('B12:I14')->getNumberFormat()->setFormatCode('0');
        foreach ([6, 7, 8, 12, 13, 14] as $row) {
            foreach (range('B', 'I') as $column) {
                $sheet->setCellValue($column.$row, null);
            }
        }

        [$maleColumn, $femaleColumn] = $this->heiSummaryColumns();
        foreach (['CWTS' => [6, 12], 'LTS' => [7, 13]] as $component => [$enrollmentRow, $graduateRow]) {
            $componentPassers = $passers->filter(fn (NstpEnrollment $item): bool => $item->component->code === $component);
            $male = $componentPassers->filter(fn (NstpEnrollment $item): bool => $this->sexCode($item->student->studentProfile?->sex) === 'M')->count();
            $female = $componentPassers->filter(fn (NstpEnrollment $item): bool => $this->sexCode($item->student->studentProfile?->sex) === 'F')->count();

            foreach ([$enrollmentRow, $graduateRow] as $row) {
                $sheet->setCellValue($maleColumn.$row, $male);
                $sheet->setCellValue($femaleColumn.$row, $female);
            }
        }
    }

    /** @param array<int, array<int, mixed>> $rows */
    private function writeDetailSheet(?Worksheet $sheet, array $rows): void
    {
        if (! $sheet) {
            throw new RuntimeException('The CHED template is missing a required detail worksheet.');
        }

        $lastTemplateRow = max(3, $sheet->getHighestRow());
        if ($rows === []) {
            return;
        }

        $sheet->fromArray($rows, null, 'A3', true);
        $lastRow = count($rows) + 2;
        for ($row = 3; $row <= $lastRow; $row++) {
            if ($row > $lastTemplateRow) {
                $sheet->duplicateStyle($sheet->getStyle('A3:T3'), "A{$row}:T{$row}");
            }
            $sheet->getCell("E{$row}")->setValueExplicit('', DataType::TYPE_STRING);
        }
        $sheet->getStyle("J3:J{$lastRow}")->getNumberFormat()->setFormatCode('mm/dd/yyyy');
    }

    /** @return array{string, string} */
    private function heiSummaryColumns(): array
    {
        return match (strtoupper(preg_replace('/[^A-Z]/i', '', (string) config('ched.hei_type')))) {
            'PRIVATE' => ['B', 'C'],
            'LUCS' => ['F', 'G'],
            'OGS' => ['H', 'I'],
            default => ['D', 'E'],
        };
    }

    private function sexCode(?string $sex): string
    {
        return match (strtolower(trim((string) $sex))) {
            'male', 'm' => 'M',
            'female', 'f' => 'F',
            default => '',
        };
    }

    private function yearLevel(?string $yearSection): string
    {
        return preg_match('/\d+/', (string) $yearSection, $matches) ? $matches[0] : (string) $yearSection;
    }

    private function loadTemplate(string $path): Spreadsheet
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'ched-report-template-');
        if ($temporaryPath === false || file_put_contents($temporaryPath, file_get_contents($path)) === false) {
            throw new RuntimeException('The CHED semestral report template could not be prepared.');
        }

        register_shutdown_function(static fn () => @unlink($temporaryPath));

        return IOFactory::load($temporaryPath);
    }
}
