<?php

namespace Tests\Feature;

use App\Mail\AccountCreatedMail;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\StudentImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class StudentImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_and_nstp_admin_can_open_import_page_and_download_template(): void
    {
        foreach ([
            'super_admin' => ['/admin/students/import', '/admin/students'],
            'nstp_admin' => ['/nstp-admin/students/import', '/nstp-admin/students'],
        ] as $role => [$url, $directoryUrl]) {
            $user = User::factory()->create(['role' => $role, 'status' => 'active']);

            $this->actingAs($user)->get($directoryUrl)
                ->assertOk()
                ->assertSee('Import Students')
                ->assertSee('aria-label="Import students from Excel"', false)
                ->assertSee('href="'.url($url).'"', false);

            $this->actingAs($user)->get($url)
                ->assertOk()
                ->assertSee('Upload an Excel student list')
                ->assertSee('Download Excel template')
                ->assertSee('Import & download credentials')
                ->assertSee('Import & view credentials');

            $this->actingAs($user)->get($url.'/template')
                ->assertOk()
                ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        }
    }

    public function test_both_authorized_roles_can_import_excel_student_accounts(): void
    {
        Mail::fake();

        foreach (['super_admin' => '/admin/students/import', 'nstp_admin' => '/nstp-admin/students/import'] as $index => $url) {
            $user = User::factory()->create(['role' => $index, 'status' => 'active']);
            $email = str_replace('_', '.', $index).'@import.test';
            $file = $this->excelFile([
                StudentImportService::HEADERS,
                $this->validStudentRow([
                    'last_name' => ucwords(str_replace('_', ' ', $index)),
                    'first_name' => 'Imported',
                    'middle_name' => '',
                    'email' => $email,
                    'student_number' => $index === 'super_admin' ? '2026000001' : '2026000002',
                ]),
            ]);

            $response = $this->actingAs($user)->post($url, ['file' => $file, 'credential_delivery' => 'download']);
            $response->assertOk()
                ->assertDownload()
                ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->assertHeader('x-imported-students', '1')
                ->assertHeader('x-credential-emails-queued', '1')
                ->assertHeader('x-credential-emails-failed', '0');

            $credentials = $this->credentialsFromResponse($response->streamedContent());
            $this->assertSame($email, $credentials['email']);
            $this->assertMatchesRegularExpression('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{12,}$/', $credentials['password']);
            $this->assertSame('Attendance QR', $credentials['qr_heading']);
            $this->assertSame(1, $credentials['qr_images']);

            $student = User::where('email', $email)->firstOrFail();
            $this->assertSame('student', $student->role);
            $this->assertSame('active', $student->status);
            $this->assertTrue($student->must_change_password);
            $this->assertTrue($student->must_upload_student_documents);
            $this->assertNotEmpty($student->student_qr_token);
            $this->assertTrue(Hash::check($credentials['password'], $student->password));
            $this->assertInstanceOf(StudentProfile::class, $student->studentProfile);
            $this->assertSame(
                $index === 'super_admin' ? '2026000001' : '2026000002',
                $student->studentProfile?->student_number,
            );
            $this->assertSame('College of Engineering and Technology', $student->studentProfile?->college);
            $this->assertSame('Bachelor of Science in Information Technology', $student->studentProfile?->course);

            $directory = $index === 'super_admin' ? '/admin/students' : '/nstp-admin/students';
            $this->actingAs($user)->get($directory)
                ->assertOk()
                ->assertSee($student->name)
                ->assertSee('View QR')
                ->assertSee('Download QR')
                ->assertSee('data-qr-url', false)
                ->assertDontSee('<img src="'.url($directory.'/'.$student->id.'/qr'), false);

            Cache::flush();
            $this->actingAs($user)->get($directory.'/'.$student->id.'/qr')
                ->assertOk()->assertHeader('content-type', 'image/svg+xml');
            $this->assertTrue(Cache::has('student-attendance-qr:'.hash('sha256', $student->student_qr_token)));
            $this->assertDatabaseMissing('audit_logs', [
                'route_name' => $index === 'super_admin' ? 'admin.students.qr' : 'nstp_admin.students.qr',
            ]);
            $this->actingAs($user)->get($directory.'/'.$student->id.'/qr/download')
                ->assertOk()->assertHeader('content-disposition', 'attachment; filename="'.str($student->name)->slug().'-attendance-qr.svg"');

            Mail::assertSent(AccountCreatedMail::class, fn (AccountCreatedMail $mail): bool => $mail->hasTo($student->email)
                && $mail->recipientName === $student->name
                && $mail->roleLabel === 'Student'
            );
        }

        Mail::assertSent(AccountCreatedMail::class, 2);
    }

    public function test_authorized_user_can_view_generated_credentials_instead_of_downloading_them(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $file = $this->excelFile([
            StudentImportService::HEADERS,
            $this->validStudentRow([
                'last_name' => 'Student',
                'first_name' => 'Viewed',
                'middle_name' => '',
                'email' => 'viewed.student@import.test',
                'student_number' => '2026000003',
            ]),
        ]);

        $response = $this->actingAs($admin)->post('/admin/students/import', [
            'file' => $file,
            'credential_delivery' => 'view',
        ]);

        $response->assertOk()
            ->assertViewIs('student-import.credentials')
            ->assertHeader('cache-control', 'no-store, private')
            ->assertSee('Imported Student Credentials')
            ->assertSee('Viewed Student')
            ->assertSee('viewed.student@import.test')
            ->assertSee('Copy')
            ->assertSee('data:image/png;base64,', false)
            ->assertViewHas('credentials', function (array $credentials): bool {
                $password = $credentials[0]['temporary_password'];
                $student = User::where('email', 'viewed.student@import.test')->firstOrFail();

                return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{12,}$/', $password) === 1
                    && Hash::check($password, $student->password);
            });
    }

    public function test_import_is_all_or_nothing_and_reports_row_errors(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        User::factory()->create(['email' => 'existing@example.test']);

        $file = $this->excelFile([
            StudentImportService::HEADERS,
            $this->validStudentRow([
                'last_name' => 'Student',
                'first_name' => 'Valid',
                'middle_name' => '',
                'email' => 'valid@example.test',
                'student_number' => '2026000004',
            ]),
            $this->validStudentRow([
                'last_name' => 'Student',
                'first_name' => 'Duplicate',
                'middle_name' => '',
                'email' => 'existing@example.test',
                'student_number' => '2026000005',
            ]),
        ]);

        $this->actingAs($admin)->from('/admin/students/import')->post('/admin/students/import', ['file' => $file, 'credential_delivery' => 'view'])
            ->assertRedirect('/admin/students/import')
            ->assertSessionHasErrors('import_rows');

        $this->assertDatabaseMissing('users', ['email' => 'valid@example.test']);
        $this->assertDatabaseMissing('student_profiles', ['student_number' => '2026000004']);
    }

    public function test_import_rejects_a_row_when_required_student_profile_data_is_missing(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $file = $this->excelFile([
            StudentImportService::HEADERS,
            $this->validStudentRow([
                'email' => 'missing-contact@example.test',
                'student_number' => '2026000006',
                'contact_number' => '',
            ]),
        ]);

        $this->actingAs($admin)
            ->from('/admin/students/import')
            ->post('/admin/students/import', ['file' => $file])
            ->assertRedirect('/admin/students/import')
            ->assertSessionHasErrors('import_rows');

        $this->assertDatabaseMissing('users', ['email' => 'missing-contact@example.test']);
        $this->assertDatabaseMissing('student_profiles', ['student_number' => '2026000006']);
    }

    public function test_downloaded_template_contains_every_student_profile_column_and_instruction(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $response = $this->actingAs($admin)->get('/admin/students/import/template');
        $path = tempnam(sys_get_temp_dir(), 'student-template-').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        $spreadsheet = IOFactory::load($path);
        $this->assertSame(StudentImportService::HEADERS, $spreadsheet->getSheetByName('Student Import')->rangeToArray('A1:AD1')[0]);
        $this->assertSame('last_name', $spreadsheet->getSheetByName('Instructions')->getCell('A2')->getValue());
        $this->assertSame('year_section', $spreadsheet->getSheetByName('Instructions')->getCell('A31')->getValue());
        $spreadsheet->disconnectWorksheets();
        unlink($path);
    }

    public function test_other_account_roles_cannot_import_students(): void
    {
        $file = UploadedFile::fake()->create('students.xlsx', 10, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        foreach (['student', 'facilitator', 'coordinator'] as $role) {
            $user = User::factory()->create(['role' => $role, 'status' => 'active']);
            $this->actingAs($user)->get('/admin/students/import')->assertForbidden();
            $this->actingAs($user)->post('/nstp-admin/students/import', ['file' => $file])->assertForbidden();
        }
    }

    /** @param array<int, array<int, string>> $rows */
    private function excelFile(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows);
        $path = tempnam(sys_get_temp_dir(), 'student-import-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile(
            $path,
            'students.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
    }

    /** @return array<int, string> */
    private function validStudentRow(array $overrides = []): array
    {
        $data = array_replace([
            'last_name' => 'Dela Cruz',
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'extension_name' => '',
            'province' => 'Pangasinan',
            'province_code' => '015500000',
            'city_municipality' => 'Lingayen',
            'city_municipality_code' => '015522000',
            'barangay' => 'Poblacion',
            'barangay_code' => '015522001',
            'date_of_birth' => '2006-05-20',
            'birth_province' => 'Pangasinan',
            'birth_province_code' => '015500000',
            'birth_city_municipality' => 'Lingayen',
            'birth_city_municipality_code' => '015522000',
            'religion' => 'Roman Catholic',
            'sex' => 'Male',
            'blood_type' => 'O+',
            'contact_number' => '09171234567',
            'email' => 'juan@example.test',
            'emergency_contact_name' => 'Maria Dela Cruz',
            'emergency_relationship' => 'Mother',
            'emergency_contact_number' => '09981234567',
            'emergency_same_address' => 'Yes',
            'emergency_address' => '',
            'student_number' => '2026000099',
            'college' => 'College of Engineering and Technology',
            'course' => 'Bachelor of Science in Information Technology',
            'major' => 'N/A',
            'year_section' => '1A',
        ], $overrides);

        return array_map(fn (string $header): string => (string) $data[$header], StudentImportService::HEADERS);
    }

    /** @return array{email: string, password: string, qr_heading: string, qr_images: int} */
    private function credentialsFromResponse(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'student-credentials-').'.xlsx';
        file_put_contents($path, $content);
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $credentials = [
            'email' => (string) $sheet->getCell('B5')->getValue(),
            'password' => (string) $sheet->getCell('C5')->getValue(),
            'qr_heading' => (string) $sheet->getCell('D4')->getValue(),
            'qr_images' => count($sheet->getDrawingCollection()),
        ];
        $spreadsheet->disconnectWorksheets();
        unlink($path);

        return $credentials;
    }
}
