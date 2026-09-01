<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_and_database_backup_are_grouped_under_super_admin_administration(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($admin)->get('/admin/database-backup')
            ->assertOk()
            ->assertSee('Download SQL backup')
            ->assertSeeTextInOrder([
                'Administration',
                'Reports',
                'Database Backup',
                'System Logs',
                'Attendance & Learning',
            ]);
    }

    public function test_super_admin_can_download_a_restorable_sql_backup_without_database_credentials(): void
    {
        config(['database.connections.sqlite.password' => 'NEVER_INCLUDE_THIS_PASSWORD']);
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'status' => 'active',
            'email' => 'backup-admin@example.test',
        ]);

        $response = $this->actingAs($admin)->post('/admin/database-backup/download');

        $response->assertOk()
            ->assertHeader('content-type', 'application/sql; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename=snapie-database-', (string) $response->headers->get('content-disposition'));

        $backup = $response->streamedContent();
        $this->assertStringContainsString('SNAPIE database backup', $backup);
        $this->assertStringContainsString('CREATE TABLE', $backup);
        $this->assertStringContainsString('backup-admin@example.test', $backup);
        $this->assertStringContainsString('COMMIT;', $backup);
        $this->assertStringNotContainsString('NEVER_INCLUDE_THIS_PASSWORD', $backup);
    }

    public function test_non_super_admin_cannot_open_or_download_database_backup(): void
    {
        $nstpAdmin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);

        $this->actingAs($nstpAdmin)->get('/admin/database-backup')->assertForbidden();
        $this->actingAs($nstpAdmin)->post('/admin/database-backup/download')->assertForbidden();
    }
}
