<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatabaseBackupController extends Controller
{
    public function __construct(private DatabaseBackupService $backups) {}

    public function index(): View
    {
        $archives = $this->backups->archives();

        return view('admin.database-backup', [
            'database' => $this->backups->information(),
            'archives' => $archives,
            'archiveSize' => (int) $archives->sum('size'),
        ]);
    }

    public function download(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            foreach ($this->backups->stream() as $chunk) {
                echo $chunk;
            }
        }, $this->backups->filename(), [
            'Content-Type' => 'application/sql; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function archive(): RedirectResponse
    {
        $archive = $this->backups->archive();

        return back()->with('status', 'Database archive '.$archive['name'].' created successfully.');
    }

    public function upload(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'database_file' => ['required', 'file', 'extensions:sql', 'max:102400'],
            'action' => ['required', Rule::in(['archive', 'restore'])],
            'confirmation' => ['nullable', 'required_if:action,restore', 'in:RESTORE'],
        ], [
            'database_file.extensions' => 'The uploaded database backup must use the .sql extension.',
            'confirmation.required_if' => 'Type RESTORE to upload and restore this database backup.',
            'confirmation.in' => 'Type RESTORE exactly to upload and restore this database backup.',
        ]);
        $archive = $this->backups->import($request->file('database_file'));

        if ($validated['action'] === 'restore') {
            $safetyArchive = $this->backups->restore($archive['name']);

            return redirect()->route('admin.database-backup.index')->with(
                'status',
                'Uploaded and restored '.$archive['name'].'. A pre-restore safety archive was saved as '.$safetyArchive['name'].'.'
            );
        }

        return back()->with('status', 'Uploaded database backup saved as '.$archive['name'].'.');
    }

    public function downloadArchive(string $archive): StreamedResponse
    {
        $details = $this->backups->details($archive);

        return Storage::disk('local')->download($details['path'], $details['name'], [
            'Content-Type' => 'application/sql; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function restore(Request $request, string $archive): RedirectResponse
    {
        $request->validate([
            'confirmation' => ['required', 'in:RESTORE'],
        ], [
            'confirmation.required' => 'Type RESTORE to confirm the database replacement.',
            'confirmation.in' => 'Type RESTORE exactly to confirm the database replacement.',
        ]);
        $safetyArchive = $this->backups->restore($archive);

        return redirect()->route('admin.database-backup.index')->with(
            'status',
            'Database restored from '.$archive.'. A pre-restore safety archive was saved as '.$safetyArchive['name'].'.'
        );
    }

    public function destroy(Request $request, string $archive): RedirectResponse
    {
        $request->validate([
            'confirmation' => ['required', 'in:DELETE'],
        ], [
            'confirmation.required' => 'Type DELETE to confirm archive deletion.',
            'confirmation.in' => 'Type DELETE exactly to confirm archive deletion.',
        ]);
        $this->backups->delete($archive);

        return back()->with('status', 'Database archive '.$archive.' was permanently deleted.');
    }
}
