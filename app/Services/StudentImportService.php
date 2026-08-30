<?php

namespace App\Services;

use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class StudentImportService
{
    public const HEADERS = [
        'full_name', 'email', 'status',
        'component_code', 'academic_year', 'semester', 'section_code',
    ];

    /**
     * @return array{
     *     students: int,
     *     enrollments: int,
     *     credentials: array<int, array{name: string, email: string, temporary_password: string, component: string, section: string}>
     * }
     */
    public function import(UploadedFile $file): array
    {
        $rows = $this->readRows($file);

        if (count($rows) < 2) {
            throw ValidationException::withMessages(['file' => 'The spreadsheet does not contain any student rows.']);
        }

        if (count($rows) > 1001) {
            throw ValidationException::withMessages(['file' => 'The spreadsheet exceeds the maximum of 1,000 student rows.']);
        }

        $headers = array_map(fn ($value) => $this->normalizeHeader($value), array_shift($rows));
        $missingHeaders = array_diff(self::HEADERS, $headers);

        if ($missingHeaders !== []) {
            throw ValidationException::withMessages([
                'file' => 'Missing required column(s): '.implode(', ', $missingHeaders).'. Download and use the provided template.',
            ]);
        }

        $headerIndexes = array_flip($headers);
        $prepared = [];
        $rowErrors = [];
        $emailsInFile = [];
        $sectionAssignments = [];

        foreach ($rows as $offset => $row) {
            $excelRow = $offset + 2;
            $data = [];

            foreach (self::HEADERS as $header) {
                $data[$header] = trim((string) ($row[$headerIndexes[$header]] ?? ''));
            }

            if (collect($data)->every(fn ($value) => $value === '')) {
                continue;
            }

            $data['email'] = str($data['email'])->lower()->toString();
            $data['status'] = str($data['status'] ?: 'active')->lower()->toString();
            $data['component_code'] = str($data['component_code'])->upper()->toString();
            $data['semester'] = str($data['semester'])->lower()->replace([' semester', ' term'], '')->toString();
            $data['section_code'] = str($data['section_code'])->upper()->toString();

            $validator = Validator::make($data, [
                'full_name' => ['required', 'string', 'max:100'],
                'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
                'status' => ['required', Rule::in(array_keys(User::STATUS_LABELS))],
                'component_code' => ['nullable', 'string', 'max:10'],
                'academic_year' => ['nullable', 'required_with:component_code', 'regex:/^\d{4}-\d{4}$/'],
                'semester' => ['nullable', 'required_with:component_code', Rule::in(array_keys(NstpSection::SEMESTERS))],
                'section_code' => ['nullable', 'string', 'max:30'],
            ], [
                'academic_year.regex' => 'The academic year must use the format 2026-2027.',
            ]);

            $errors = $validator->errors()->all();

            if (isset($emailsInFile[$data['email']])) {
                $errors[] = "The email is also used on row {$emailsInFile[$data['email']]}.";
            } else {
                $emailsInFile[$data['email']] = $excelRow;
            }

            $component = null;
            $section = null;

            if ($data['component_code'] !== '') {
                $component = NstpComponent::whereRaw('UPPER(code) = ?', [$data['component_code']])->where('is_active', true)->first();

                if (! $component) {
                    $errors[] = "Component code {$data['component_code']} is not active or does not exist.";
                }

                if ($this->hasInvalidAcademicYearSequence($data['academic_year'])) {
                    $errors[] = 'The academic year must contain consecutive years, for example 2026-2027.';
                }

                if ($data['section_code'] !== '' && $component && $data['academic_year'] !== '' && $data['semester'] !== '') {
                    $section = NstpSection::query()
                        ->where('component_id', $component->id)
                        ->whereRaw('UPPER(code) = ?', [$data['section_code']])
                        ->where('academic_year', $data['academic_year'])
                        ->where('semester', $data['semester'])
                        ->where('status', 'active')
                        ->first();

                    if (! $section) {
                        $errors[] = "Section {$data['section_code']} does not match the selected component and term.";
                    } else {
                        $sectionAssignments[$section->id] = ($sectionAssignments[$section->id] ?? 0) + 1;
                        if ($section->enrollments()->where('status', 'enrolled')->count() + $sectionAssignments[$section->id] > $section->capacity) {
                            $errors[] = "Section {$section->code} does not have enough remaining capacity.";
                        }
                    }
                }
            } elseif ($data['academic_year'] !== '' || $data['semester'] !== '' || $data['section_code'] !== '') {
                $errors[] = 'Component code is required when academic year, semester, or section code is supplied.';
            }

            if ($errors !== []) {
                foreach ($errors as $error) {
                    $rowErrors[] = "Row {$excelRow}: {$error}";
                }

                continue;
            }

            $prepared[] = compact('data', 'component', 'section');
        }

        if ($rowErrors !== []) {
            throw ValidationException::withMessages(['import_rows' => $rowErrors]);
        }

        if ($prepared === []) {
            throw ValidationException::withMessages(['file' => 'The spreadsheet does not contain any valid student rows.']);
        }

        return DB::transaction(function () use ($prepared): array {
            $enrollmentCount = 0;
            $credentials = [];

            foreach ($prepared as $item) {
                $data = $item['data'];
                $temporaryPassword = $this->generateTemporaryPassword();
                $student = User::create([
                    'name' => $data['full_name'],
                    'email' => $data['email'],
                    'password' => $temporaryPassword,
                    'role' => 'student',
                    'status' => $data['status'],
                    'must_change_password' => true,
                ]);

                if ($item['component']) {
                    NstpEnrollment::create([
                        'student_id' => $student->id,
                        'component_id' => $item['component']->id,
                        'section_id' => $item['section']?->id,
                        'academic_year' => $data['academic_year'],
                        'semester' => $data['semester'],
                        'status' => 'enrolled',
                    ]);
                    $enrollmentCount++;
                }

                $credentials[] = [
                    'name' => $student->name,
                    'email' => $student->email,
                    'temporary_password' => $temporaryPassword,
                    'component' => $item['component']?->code ?? '',
                    'section' => $item['section']?->code ?? '',
                ];
            }

            return ['students' => count($prepared), 'enrollments' => $enrollmentCount, 'credentials' => $credentials];
        });
    }

    /** @return array<int, array<int, mixed>> */
    private function readRows(UploadedFile $file): array
    {
        try {
            $reader = IOFactory::createReaderForFile($file->getRealPath());
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($file->getRealPath());
            $rows = $spreadsheet->getSheet(0)->toArray(null, false, false, false);
            $spreadsheet->disconnectWorksheets();

            return $rows;
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded file could not be read. Use a valid .xlsx, .xls, or .csv file.',
            ]);
        }
    }

    private function normalizeHeader(mixed $value): string
    {
        return str((string) $value)->trim()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
    }

    private function hasInvalidAcademicYearSequence(string $academicYear): bool
    {
        if (! preg_match('/^(\d{4})-(\d{4})$/', $academicYear, $matches)) {
            return false;
        }

        return (int) $matches[2] !== (int) $matches[1] + 1;
    }

    private function generateTemporaryPassword(): string
    {
        $uppercase = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lowercase = 'abcdefghijkmnopqrstuvwxyz';
        $numbers = '23456789';
        $symbols = '!@#$%&*?';
        $pool = $uppercase.$lowercase.$numbers.$symbols;
        $characters = [
            $uppercase[random_int(0, strlen($uppercase) - 1)],
            $lowercase[random_int(0, strlen($lowercase) - 1)],
            $numbers[random_int(0, strlen($numbers) - 1)],
            $symbols[random_int(0, strlen($symbols) - 1)],
        ];

        while (count($characters) < 16) {
            $characters[] = $pool[random_int(0, strlen($pool) - 1)];
        }

        for ($index = count($characters) - 1; $index > 0; $index--) {
            $swap = random_int(0, $index);
            [$characters[$index], $characters[$swap]] = [$characters[$swap], $characters[$index]];
        }

        return implode('', $characters);
    }
}
