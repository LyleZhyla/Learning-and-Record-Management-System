<?php

namespace App\Services;

use App\Models\NstpComponent;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class StaffImportService
{
    public const HEADERS = ['name', 'email', 'role', 'component_code', 'contact_number'];

    public const ROLES = ['facilitator', 'coordinator', 'nstp_admin'];

    /**
     * @return array{
     *     accounts: int,
     *     credentials: array<int, array{name: string, email: string, role: string, role_label: string, temporary_password: string}>
     * }
     */
    public function import(UploadedFile $file): array
    {
        $rows = $this->readRows($file);

        if (count($rows) < 2) {
            throw ValidationException::withMessages(['file' => 'The spreadsheet does not contain any staff rows.']);
        }

        if (count($rows) > 1001) {
            throw ValidationException::withMessages(['file' => 'The spreadsheet exceeds the maximum of 1,000 staff rows.']);
        }

        $headers = array_map(fn ($value): string => $this->normalizeHeader($value), array_shift($rows));
        $missingHeaders = array_diff(self::HEADERS, $headers);

        if ($missingHeaders !== []) {
            throw ValidationException::withMessages([
                'file' => 'Missing required column(s): '.implode(', ', $missingHeaders).'. Download and use the provided template.',
            ]);
        }

        $headerIndexes = array_flip($headers);
        $components = NstpComponent::query()->where('is_active', true)->get()->keyBy(fn (NstpComponent $component): string => strtoupper($component->code));
        $prepared = [];
        $rowErrors = [];
        $emailsInFile = [];

        foreach ($rows as $offset => $row) {
            $excelRow = $offset + 2;
            $data = [];

            foreach (self::HEADERS as $header) {
                $data[$header] = trim((string) ($row[$headerIndexes[$header]] ?? ''));
            }

            if (collect($data)->every(fn (string $value): bool => $value === '')) {
                continue;
            }

            $data['email'] = str($data['email'])->lower()->toString();
            $data['role'] = str($data['role'])->lower()->replace([' ', '-'], '_')->toString();
            $data['component_code'] = strtoupper($data['component_code']);
            $requiresComponent = in_array($data['role'], ['facilitator', 'coordinator'], true);

            $validator = Validator::make($data, [
                'name' => ['required', 'string', 'max:100'],
                'email' => ['required', 'email:rfc', 'max:255', Rule::unique(User::class, 'email')],
                'role' => ['required', Rule::in(self::ROLES)],
                'component_code' => [Rule::requiredIf($requiresComponent), 'nullable', 'string', 'max:30'],
                'contact_number' => [Rule::requiredIf($data['role'] === 'facilitator'), 'nullable', 'regex:/^09\d{9}$/'],
            ]);

            $validator->after(function ($validator) use ($components, $data, $requiresComponent): void {
                if ($requiresComponent && ! $components->has($data['component_code'])) {
                    $validator->errors()->add('component_code', 'The component code must match an active NSTP component.');
                }

                if ($data['role'] === 'nstp_admin' && $data['component_code'] !== '') {
                    $validator->errors()->add('component_code', 'NSTP Admin accounts must not have a component code.');
                }
            });

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

            $data['nstp_component_id'] = $requiresComponent ? $components->get($data['component_code'])->id : null;
            $prepared[] = $data;
        }

        if ($rowErrors !== []) {
            throw ValidationException::withMessages(['import_rows' => $rowErrors]);
        }

        if ($prepared === []) {
            throw ValidationException::withMessages(['file' => 'The spreadsheet does not contain any valid staff rows.']);
        }

        return DB::transaction(function () use ($prepared): array {
            $roles = Role::query()->where('is_active', true)->whereIn('slug', self::ROLES)->get()->keyBy('slug');
            $credentials = [];

            foreach ($prepared as $data) {
                $temporaryPassword = $this->generateTemporaryPassword();
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => $temporaryPassword,
                    'role' => $data['role'],
                    'role_id' => $roles->get($data['role'])?->id,
                    'is_system_admin' => false,
                    'status' => 'active',
                    'nstp_component_id' => $data['nstp_component_id'],
                    'must_change_password' => true,
                ]);

                if ($user->isFacilitator()) {
                    $user->facilitatorProfile()->create(['contact_number' => $data['contact_number']]);
                }

                $credentials[] = [
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'role_label' => $user->roleLabel(),
                    'temporary_password' => $temporaryPassword,
                ];
            }

            return ['accounts' => count($prepared), 'credentials' => $credentials];
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
        return str((string) $value)->trim()->lower()->replace([' ', '-'], '_')->toString();
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
