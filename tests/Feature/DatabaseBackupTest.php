<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_and_database_backup_are_grouped_under_super_admin_administration(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($admin)->get('/admin/database-backup')
            ->assertOk()
            ->assertSee('Archive current database')
            ->assertSee('Database archives')
            ->assertSeeTextInOrder([
                'Administration',
                'Reports',
                'Database Management',
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
        $this->actingAs($nstpAdmin)->post('/admin/database-backup/archive')->assertForbidden();
    }

    public function test_super_admin_can_archive_download_and_delete_a_database_snapshot(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($admin)->post('/admin/database-backup/archive')->assertRedirect();
        $path = collect(Storage::disk('local')->files('database-archives'))->sole();
        $name = basename($path);

        $this->actingAs($admin)->get('/admin/database-backup')
            ->assertOk()->assertSee($name)->assertSee('Restore')->assertSee('Delete');
        $this->actingAs($admin)->get('/admin/database-backup/archives/'.$name.'/download')
            ->assertOk()->assertDownload($name);
        $this->actingAs($admin)->delete('/admin/database-backup/archives/'.$name, ['confirmation' => 'WRONG'])
            ->assertSessionHasErrors('confirmation');
        Storage::disk('local')->assertExists($path);
        $this->actingAs($admin)->delete('/admin/database-backup/archives/'.$name, ['confirmation' => 'DELETE'])
            ->assertRedirect();
        Storage::disk('local')->assertMissing($path);
    }

    public function test_restore_requires_explicit_confirmation(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $this->actingAs($admin)->post('/admin/database-backup/archive')->assertRedirect();
        $name = basename(collect(Storage::disk('local')->files('database-archives'))->sole());

        $this->actingAs($admin)->post('/admin/database-backup/archives/'.$name.'/restore', ['confirmation' => 'WRONG'])
            ->assertSessionHasErrors('confirmation');
    }

    public function test_restore_replaces_database_contents_and_creates_a_safety_archive(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'status' => 'active',
            'name' => 'Archived Admin Name',
        ]);
        $this->actingAs($admin)->post('/admin/database-backup/archive')->assertRedirect();
        $name = basename(collect(Storage::disk('local')->files('database-archives'))->sole());
        $admin->update(['name' => 'Changed After Archive']);

        $this->actingAs($admin)->post('/admin/database-backup/archives/'.$name.'/restore', ['confirmation' => 'RESTORE'])
            ->assertRedirect('/admin/database-backup');

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'name' => 'Archived Admin Name']);
        $this->assertCount(2, Storage::disk('local')->files('database-archives'));
    }

    public function test_super_admin_can_upload_a_snapie_backup_to_the_archive_list(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $sql = implode('', iterator_to_array(app(DatabaseBackupService::class)->stream(), false));

        $this->actingAs($admin)->post('/admin/database-backup/upload', [
            'database_file' => UploadedFile::fake()->createWithContent('external-backup.sql', $sql),
            'action' => 'archive',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertCount(1, Storage::disk('local')->files('database-archives'));
    }

    public function test_uploaded_file_can_restore_the_database_and_rejects_non_snapie_sql(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'status' => 'active',
            'name' => 'Name From Uploaded Backup',
        ]);
        $sql = implode('', iterator_to_array(app(DatabaseBackupService::class)->stream(), false));
        $admin->update(['name' => 'Changed Before Upload Restore']);

        $this->actingAs($admin)->post('/admin/database-backup/upload', [
            'database_file' => UploadedFile::fake()->createWithContent('uploaded-restore.sql', $sql),
            'action' => 'restore',
            'confirmation' => 'RESTORE',
        ])->assertRedirect('/admin/database-backup')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'name' => 'Name From Uploaded Backup']);
        $this->assertCount(2, Storage::disk('local')->files('database-archives'));

        $this->actingAs(User::findOrFail($admin->id))->post('/admin/database-backup/upload', [
            'database_file' => UploadedFile::fake()->createWithContent('malicious.sql', 'DROP TABLE users;'),
            'action' => 'archive',
        ])->assertSessionHasErrors('database_file');
    }
}
