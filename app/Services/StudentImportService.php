<?php

namespace App\Services;

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
        'name', 'email',
    ];

    /**
     * @return array{
     *     students: int,
     *     credentials: array<int, array{name: string, email: string, temporary_password: string, qr_payload: string}>
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

            $validator = Validator::make($data, [
                'name' => ['required', 'string', 'max:100'],
                'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            ]);

            $errors = $validator->errors()->all();

            if (isset($emailsInFile[$data['email']])) {
                $errors[] = "The email is also used on row {$emailsInFile[$data['email']]}.";
            } else {
                $emailsInFile[$data['email']] = $excelRow;
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

        return DB::transaction(function () use ($prepared): array {
            $credentials = [];

            foreach ($prepared as $data) {
                $temporaryPassword = $this->generateTemporaryPassword();
                $student = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => $temporaryPassword,
                    'role' => 'student',
                    'status' => 'active',
                    'must_change_password' => true,
                ]);

                $credentials[] = [
                    'name' => $student->name,
                    'email' => $student->email,
                    'temporary_password' => $temporaryPassword,
                    'qr_payload' => $student->studentQrPayload(),
                ];
            }

            return ['students' => count($prepared), 'credentials' => $credentials];
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
