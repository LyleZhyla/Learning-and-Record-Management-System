<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DatabaseBackupService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatabaseBackupController extends Controller
{
    public function __construct(private DatabaseBackupService $backups) {}

    public function index(): View
    {
        return view('admin.database-backup', ['database' => $this->backups->information()]);
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
}
