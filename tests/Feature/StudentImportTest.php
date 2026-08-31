<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\StudentImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
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
                ->assertSee('Download Excel template');

            $this->actingAs($user)->get($url.'/template')
                ->assertOk()
                ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        }
    }

    public function test_both_authorized_roles_can_import_excel_student_accounts(): void
    {
        foreach (['super_admin' => '/admin/students/import', 'nstp_admin' => '/nstp-admin/students/import'] as $index => $url) {
            $user = User::factory()->create(['role' => $index, 'status' => 'active']);
            $email = str_replace('_', '.', $index).'@import.test';
            $file = $this->excelFile([
                StudentImportService::HEADERS,
                ['Imported '.ucwords(str_replace('_', ' ', $index)), $email],
            ]);

            $response = $this->actingAs($user)->post($url, ['file' => $file]);
            $response->assertOk()
                ->assertDownload()
                ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->assertHeader('x-imported-students', '1');

            $credentials = $this->credentialsFromResponse($response->streamedContent());
            $this->assertSame($email, $credentials['email']);
            $this->assertMatchesRegularExpression('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{12,}$/', $credentials['password']);
            $this->assertSame('Attendance QR', $credentials['qr_heading']);
            $this->assertSame(1, $credentials['qr_images']);

            $student = User::where('email', $email)->firstOrFail();
            $this->assertSame('student', $student->role);
            $this->assertSame('active', $student->status);
            $this->assertTrue($student->must_change_password);
            $this->assertNotEmpty($student->student_qr_token);
            $this->assertTrue(Hash::check($credentials['password'], $student->password));

            $directory = $index === 'super_admin' ? '/admin/students' : '/nstp-admin/students';
            $this->actingAs($user)->get($directory)
                ->assertOk()->assertSee($student->name)->assertSee('Download QR');
            $this->actingAs($user)->get($directory.'/'.$student->id.'/qr')
                ->assertOk()->assertHeader('content-type', 'image/svg+xml');
            $this->actingAs($user)->get($directory.'/'.$student->id.'/qr/download')
                ->assertOk()->assertHeader('content-disposition', 'attachment; filename="'.str($student->name)->slug().'-attendance-qr.svg"');
        }
    }

    public function test_import_is_all_or_nothing_and_reports_row_errors(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        User::factory()->create(['email' => 'existing@example.test']);

        $file = $this->excelFile([
            StudentImportService::HEADERS,
            ['Valid Student', 'valid@example.test'],
            ['Duplicate Student', 'existing@example.test'],
        ]);

        $this->actingAs($admin)->from('/admin/students/import')->post('/admin/students/import', ['file' => $file])
            ->assertRedirect('/admin/students/import')
            ->assertSessionHasErrors('import_rows');

        $this->assertDatabaseMissing('users', ['email' => 'valid@example.test']);
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
