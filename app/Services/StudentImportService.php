<?php

namespace App\Services;

use App\Models\StudentProfile;
use App\Models\StudentRegistration;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Throwable;

class StudentImportService
{
    public const HEADERS = [
        'last_name',
        'first_name',
        'middle_name',
        'extension_name',
        'province',
        'province_code',
        'city_municipality',
        'city_municipality_code',
        'barangay',
        'barangay_code',
        'date_of_birth',
        'birth_province',
        'birth_province_code',
        'birth_city_municipality',
        'birth_city_municipality_code',
        'religion',
        'sex',
        'blood_type',
        'contact_number',
        'email',
        'emergency_contact_name',
        'emergency_relationship',
        'emergency_contact_number',
        'emergency_same_address',
        'emergency_address',
        'student_number',
        'college',
        'course',
        'major',
        'year_section',
    ];

    public static function templateInstructions(): array
    {
        return [
            ['last_name', 'Required; maximum 100 characters', 'Dela Cruz'],
            ['first_name', 'Required; maximum 100 characters', 'Juan'],
            ['middle_name', 'Optional; leave blank if not applicable', 'Santos'],
            ['extension_name', 'Optional; leave blank if not applicable', 'Jr.'],
            ['province', 'Required; current address province', 'Pangasinan'],
            ['province_code', 'Required; valid PSGC province code', '015500000'],
            ['city_municipality', 'Required; current city or municipality', 'Lingayen'],
            ['city_municipality_code', 'Required; PSGC city/municipality code', '015522000'],
            ['barangay', 'Required; current barangay', 'Poblacion'],
            ['barangay_code', 'Required; PSGC barangay code', '015522001'],
            ['date_of_birth', 'Required; YYYY-MM-DD and before today', '2006-05-20'],
            ['birth_province', 'Required; province of birth', 'Pangasinan'],
            ['birth_province_code', 'Required; valid PSGC province code', '015500000'],
            ['birth_city_municipality', 'Required; city/municipality of birth', 'Lingayen'],
            ['birth_city_municipality_code', 'Required; PSGC city/municipality code', '015522000'],
            ['religion', 'Required; listed religion or the specific value for Others', 'Roman Catholic'],
            ['sex', 'Required; Male or Female', 'Male'],
            ['blood_type', 'Required; A+, A-, B+, B-, AB+, AB-, O+, O-, or Unknown', 'O+'],
            ['contact_number', 'Required; 11 digits beginning with 09', '09171234567'],
            ['email', 'Required; valid and unique login email', 'juan@example.edu.ph'],
            ['emergency_contact_name', 'Required; maximum 150 characters', 'Maria Dela Cruz'],
            ['emergency_relationship', 'Required; use an accepted relationship', 'Mother'],
            ['emergency_contact_number', 'Required; 11 digits beginning with 09', '09981234567'],
            ['emergency_same_address', 'Required; Yes/No, True/False, or 1/0', 'Yes'],
            ['emergency_address', 'Required when emergency_same_address is No; otherwise optional', 'Lingayen, Pangasinan'],
            ['student_number', 'Required; 10 digits beginning with 20 and unique', '2026000001'],
            ['college', 'Required; must match a configured college exactly', 'College of Engineering and Technology'],
            ['course', 'Required; must belong to the selected college', 'Bachelor of Science in Information Technology'],
            ['major', 'Required; use N/A when the course has no major', 'N/A'],
            ['year_section', 'Required; 1A–1F or a specific custom section', '1A'],
        ];
    }

    public function __construct(private readonly AccountCredentialMailer $credentialMailer) {}

    /**
     * @return array{
     *     students: int,
     *     credentials: array<int, array{name: string, email: string, temporary_password: string, qr_payload: string}>,
     *     emails_queued: int,
     *     emails_failed: int
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
        $studentNumbersInFile = [];

        foreach ($rows as $offset => $row) {
            $excelRow = $offset + 2;
            $data = [];

            foreach (self::HEADERS as $header) {
                $data[$header] = $this->normalizeValue($header, $row[$headerIndexes[$header]] ?? null);
            }

            if (collect($data)->every(fn ($value) => $value === '')) {
                continue;
            }

            $data['email'] = str($data['email'])->lower()->toString();

            $validator = Validator::make($data, [
                'last_name' => ['required', 'string', 'max:100'],
                'first_name' => ['required', 'string', 'max:100'],
                'middle_name' => ['nullable', 'string', 'max:100'],
                'extension_name' => ['nullable', 'string', 'max:30'],
                'province' => ['required', 'string', 'max:120'],
                'province_code' => ['required', 'string', Rule::in(array_keys(config('philippine_locations.provinces', [])))],
                'city_municipality' => ['required', 'string', 'max:120'],
                'city_municipality_code' => ['required', 'string', 'max:12'],
                'barangay' => ['required', 'string', 'max:120'],
                'barangay_code' => ['required', 'string', 'max:12'],
                'date_of_birth' => ['required', 'date', 'before:today'],
                'birth_province' => ['required', 'string', 'max:120'],
                'birth_province_code' => ['required', 'string', Rule::in(array_keys(config('philippine_locations.provinces', [])))],
                'birth_city_municipality' => ['required', 'string', 'max:120'],
                'birth_city_municipality_code' => ['required', 'string', 'max:12'],
                'religion' => ['required', 'string', 'max:120'],
                'sex' => ['required', Rule::in(['Male', 'Female'])],
                'blood_type' => ['required', Rule::in(config('student_details.blood_types', []))],
                'contact_number' => ['required', 'regex:/^09\d{9}$/'],
                'email' => [
                    'required', 'email:rfc', 'max:255',
                    Rule::unique(User::class, 'email'),
                    Rule::unique(StudentRegistration::class, 'email'),
                ],
                'emergency_contact_name' => ['required', 'string', 'max:150'],
                'emergency_relationship' => ['required', Rule::in(config('student_details.relationships', []))],
                'emergency_contact_number' => ['required', 'regex:/^09\d{9}$/'],
                'emergency_same_address' => ['required', 'boolean'],
                'emergency_address' => [Rule::requiredIf($data['emergency_same_address'] !== true), 'nullable', 'string', 'max:500'],
                'student_number' => [
                    'required', 'regex:/^20\d{8}$/',
                    Rule::unique(StudentProfile::class, 'student_number'),
                    Rule::unique(StudentRegistration::class, 'student_number'),
                ],
                'college' => ['required', Rule::in(array_keys(config('academics.colleges', [])))],
                'course' => ['required', 'string', 'max:150'],
                'major' => ['required', 'string', 'max:150'],
                'year_section' => ['required', 'string', 'max:80'],
            ]);

            $validator->after(function ($validator) use ($data): void {
                $programs = config('academics.colleges', [])[$data['college']] ?? null;
                $majors = is_array($programs) ? ($programs[$data['course']] ?? null) : null;

                if (! is_array($majors)) {
                    $validator->errors()->add('course', 'The selected course is not offered by the selected college.');
                } elseif (! in_array($data['major'], $majors, true)) {
                    $validator->errors()->add('major', 'The selected major is not available for the selected course.');
                }
            });

            $errors = $validator->errors()->all();

            if (isset($emailsInFile[$data['email']])) {
                $errors[] = "The email is also used on row {$emailsInFile[$data['email']]}.";
            } else {
                $emailsInFile[$data['email']] = $excelRow;
            }

            if (isset($studentNumbersInFile[$data['student_number']])) {
                $errors[] = "The student number is also used on row {$studentNumbersInFile[$data['student_number']]}.";
            } else {
                $studentNumbersInFile[$data['student_number']] = $excelRow;
            }

            if ($errors !== []) {
                foreach ($errors as $error) {
                    $rowErrors[] = "Row {$excelRow}: {$error}";
                }

                continue;
            }

            $prepared[] = $data;
        }

        if ($rowErrors !== []) {
            throw ValidationException::withMessages(['import_rows' => $rowErrors]);
        }

        if ($prepared === []) {
            throw ValidationException::withMessages(['file' => 'The spreadsheet does not contain any valid student rows.']);
        }

        $result = DB::transaction(function () use ($prepared): array {
            $credentials = [];

            foreach ($prepared as $data) {
                $temporaryPassword = $this->generateTemporaryPassword();
                $student = User::create([
                    'name' => collect([
                        $data['first_name'],
                        $data['middle_name'],
                        $data['last_name'],
                        $data['extension_name'],
                    ])->filter(fn ($part) => filled($part))->implode(' '),
                    'email' => $data['email'],
                    'password' => $temporaryPassword,
                    'role' => 'student',
                    'status' => 'active',
                    'must_change_password' => true,
                    'must_upload_student_documents' => true,
                ]);
                $student->studentProfile()->create(collect($data)->except('email')->all());

                $credentials[] = [
                    'name' => $student->name,
                    'email' => $student->email,
                    'temporary_password' => $temporaryPassword,
                    'qr_payload' => $student->studentQrPayload(),
                ];
            }

            return ['students' => count($prepared), 'credentials' => $credentials];
        });

        $users = User::query()
            ->whereIn('email', array_column($result['credentials'], 'email'))
            ->get()
            ->keyBy('email');
        $emailsQueued = 0;

        foreach ($result['credentials'] as $credential) {
            $student = $users->get($credential['email']);

            if ($student && $this->credentialMailer->send($student, $credential['temporary_password'])) {
                $emailsQueued++;
            }
        }

        return $result + [
            'emails_queued' => $emailsQueued,
            'emails_failed' => count($result['credentials']) - $emailsQueued,
        ];
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

    private function normalizeValue(string $header, mixed $value): mixed
    {
        if ($header === 'date_of_birth' && is_numeric($value)) {
            try {
                return Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (Throwable) {
                return trim((string) $value);
            }
        }

        $normalized = trim((string) ($value ?? ''));

        if (str_ends_with($header, '_code') && ctype_digit($normalized) && strlen($normalized) < 9) {
            return str_pad($normalized, 9, '0', STR_PAD_LEFT);
        }

        if (in_array($header, ['contact_number', 'emergency_contact_number'], true)
            && preg_match('/^9\d{9}$/', $normalized) === 1) {
            return '0'.$normalized;
        }

        if ($header === 'emergency_same_address') {
            return match (strtolower($normalized)) {
                'yes', 'y', 'true', '1' => true,
                'no', 'n', 'false', '0' => false,
                default => $normalized,
            };
        }

        return $normalized;
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
