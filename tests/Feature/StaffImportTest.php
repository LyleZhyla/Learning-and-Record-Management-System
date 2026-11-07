<?php

namespace Tests\Feature;

use App\Models\NstpComponent;
use App\Models\User;
use App\Services\StaffImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class StaffImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_open_staff_import_and_download_template(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($admin)->get('/admin/users')
            ->assertOk()
            ->assertSee('Import staff')
            ->assertSee('href="'.url('/admin/users/import').'"', false);

        $this->actingAs($admin)->get('/admin/users/import')
            ->assertOk()
            ->assertSee('Bulk Staff Import')
            ->assertSee('facilitator, coordinator, or nstp_admin')
            ->assertSee('No automatic email');

        $response = $this->actingAs($admin)->get('/admin/users/import/template')
            ->assertOk()
            ->assertDownload('staff-account-import-template.xlsx');

        $path = tempnam(sys_get_temp_dir(), 'staff-template-').'.xlsx';
        file_put_contents($path, $response->streamedContent());
        $spreadsheet = IOFactory::load($path);
        $this->assertSame(StaffImportService::HEADERS, $spreadsheet->getSheetByName('Staff Import')->rangeToArray('A1:E1')[0]);
        $this->assertSame('Instructions', $spreadsheet->getSheet(1)->getTitle());
        $spreadsheet->disconnectWorksheets();
        unlink($path);
    }

    public function test_super_admin_can_bulk_import_supported_staff_accounts_without_sending_email(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $cwts = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'default_section_capacity' => 40, 'is_active' => true]);
        $lts = NstpComponent::create(['code' => 'LTS', 'name' => 'Literacy Training Service', 'default_section_capacity' => 40, 'is_active' => true]);
        $file = $this->excelFile([
            StaffImportService::HEADERS,
            ['Imported Facilitator', 'facilitator@staff.test', 'facilitator', 'CWTS', '09171234567'],
            ['Imported Coordinator', 'coordinator@staff.test', 'coordinator', 'LTS', ''],
            ['Imported Admin', 'admin@staff.test', 'nstp_admin', '', ''],
        ]);

        $response = $this->actingAs($admin)->post('/admin/users/import', [
            'file' => $file,
            'credential_delivery' => 'view',
        ]);

        $response->assertOk()
            ->assertViewIs('admin.staff-import.credentials')
            ->assertHeader('cache-control', 'no-store, private')
            ->assertSee('3 staff accounts created')
            ->assertSee('No email was sent automatically')
            ->assertViewHas('credentials', function (array $credentials): bool {
                foreach ($credentials as $credential) {
                    $user = User::where('email', $credential['email'])->first();
                    if (! $user || ! Hash::check($credential['temporary_password'], $user->password)) {
                        return false;
                    }
                }

                return count($credentials) === 3;
            });

        $this->assertDatabaseHas('users', ['email' => 'facilitator@staff.test', 'role' => 'facilitator', 'nstp_component_id' => $cwts->id, 'status' => 'active']);
        $this->assertDatabaseHas('users', ['email' => 'coordinator@staff.test', 'role' => 'coordinator', 'nstp_component_id' => $lts->id, 'status' => 'active']);
        $this->assertDatabaseHas('users', ['email' => 'admin@staff.test', 'role' => 'nstp_admin', 'nstp_component_id' => null, 'status' => 'active']);
        $facilitator = User::where('email', 'facilitator@staff.test')->firstOrFail();
        $this->assertDatabaseHas('facilitator_profiles', ['user_id' => $facilitator->id, 'contact_number' => '09171234567']);
        $this->assertTrue($facilitator->must_change_password);
        Mail::assertNothingSent();
    }

    public function test_staff_import_is_all_or_nothing_when_a_row_is_invalid(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'default_section_capacity' => 40, 'is_active' => true]);
        $file = $this->excelFile([
            StaffImportService::HEADERS,
            ['Valid Facilitator', 'valid@staff.test', 'facilitator', 'CWTS', '09171234567'],
            ['Invalid Coordinator', 'invalid@staff.test', 'coordinator', 'UNKNOWN', ''],
        ]);

        $this->actingAs($admin)->from('/admin/users/import')->post('/admin/users/import', [
            'file' => $file,
            'credential_delivery' => 'view',
        ])->assertRedirect('/admin/users/import')->assertSessionHasErrors('import_rows');

        $this->assertDatabaseMissing('users', ['email' => 'valid@staff.test']);
        $this->assertDatabaseMissing('users', ['email' => 'invalid@staff.test']);
    }

    public function test_non_super_admin_cannot_access_staff_import(): void
    {
        foreach (['nstp_admin', 'coordinator', 'facilitator', 'student'] as $role) {
            $user = User::factory()->create(['role' => $role, 'status' => 'active']);
            $this->actingAs($user)->get('/admin/users/import')->assertForbidden();
        }
    }

    /** @param array<int, array<int, string>> $rows */
    private function excelFile(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows);
        $path = tempnam(sys_get_temp_dir(), 'staff-import-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile($path, 'staff.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}
