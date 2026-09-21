<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Services\SpreadsheetDownloadService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArchiveController extends Controller
{
    private const TYPES = [
        'attendance' => ['label' => 'Attendance records', 'description' => 'Student attendance entries from all sessions and components.', 'icon' => '▣'],
        'system-logs' => ['label' => 'System logs', 'description' => 'Authenticated activity and security audit trail entries.', 'icon' => '☷'],
        'notifications' => ['label' => 'Notifications', 'description' => 'Published announcements currently shown in notification bells.', 'icon' => '🔔'],
    ];

    public function __construct(private SpreadsheetDownloadService $downloads) {}

    public function index(): View
    {
        $groups = collect(self::TYPES)->map(function (array $details, string $type): array {
            return $details + [
                'type' => $type,
                'active_count' => $this->records($type)->count(),
                'archived_count' => $this->records($type, true)->count(),
            ];
        });

        return view('admin.archives.index', [
            'groups' => $groups,
            'recentArchives' => $this->recentArchives(),
        ]);
    }

    public function archiveAll(Request $request, string $type): RedirectResponse
    {
        $details = $this->details($type);
        $count = DB::transaction(fn () => $this->records($type)->update([
            'archived_at' => now(),
            'archived_by' => $request->user()->id,
        ]));

        return back()->with('status', number_format($count).' '.$details['label'].' archived successfully.');
    }

    public function restoreAll(string $type): RedirectResponse
    {
        $details = $this->details($type);
        $count = DB::transaction(fn () => $this->records($type, true)->update([
            'archived_at' => null,
            'archived_by' => null,
        ]));

        return back()->with('status', number_format($count).' '.$details['label'].' restored successfully.');
    }

    public function destroyAll(Request $request, string $type): RedirectResponse
    {
        $details = $this->details($type);
        $request->validate([
            'confirmation' => ['required', 'in:DELETE'],
        ], [
            'confirmation.in' => 'Type DELETE exactly to permanently remove the archived records.',
            'confirmation.required' => 'Type DELETE to confirm permanent deletion.',
        ]);

        $count = DB::transaction(fn () => $this->records($type, true)->delete());

        return back()->with(
            'status',
            number_format($count).' archived '.$details['label'].' permanently deleted. These records can no longer be restored.'
        );
    }

    public function export(string $type): StreamedResponse
    {
        $details = $this->details($type);
        $records = $this->records($type, true)->get();

        [$headers, $rows] = match ($type) {
            'attendance' => [
                ['Student', 'Session', 'Status', 'Checked In', 'Checked Out', 'Source', 'Archived At'],
                $records->load(['student', 'attendanceSession'])->map(fn (AttendanceRecord $record) => [
                    'student' => $record->student?->name ?? 'Deleted student',
                    'session' => $record->attendanceSession?->title ?? 'Deleted session',
                    'status' => ucfirst($record->status),
                    'checked_in' => $record->checked_in_at?->format('M d, Y h:i A') ?? '—',
                    'checked_out' => $record->checked_out_at?->format('M d, Y h:i A') ?? '—',
                    'source' => strtoupper($record->source),
                    'archived_at' => $record->archived_at?->format('M d, Y h:i A') ?? '—',
                ]),
            ],
            'system-logs' => [
                ['Actor', 'Email', 'Action', 'Description', 'Request', 'Status', 'Created At', 'Archived At'],
                $records->map(fn (AuditLog $log) => [
                    'actor' => $log->actor_name,
                    'email' => $log->actor_email,
                    'action' => str($log->action)->headline(),
                    'description' => $log->description,
                    'request' => $log->method.' '.($log->route_name ?? $log->path),
                    'status' => $log->status_code,
                    'created_at' => $log->created_at?->format('M d, Y h:i A') ?? '—',
                    'archived_at' => $log->archived_at?->format('M d, Y h:i A') ?? '—',
                ]),
            ],
            'notifications' => [
                ['Title', 'Audience', 'Component', 'Status', 'Published At', 'Expires At', 'Archived At'],
                $records->load('component')->map(fn (Announcement $announcement) => [
                    'title' => $announcement->title,
                    'audience' => $announcement->audienceLabel(),
                    'component' => $announcement->component?->code ?? 'All',
                    'status' => ucfirst($announcement->status),
                    'published_at' => $announcement->published_at?->format('M d, Y h:i A') ?? '—',
                    'expires_at' => $announcement->expires_at?->format('M d, Y h:i A') ?? '—',
                    'archived_at' => $announcement->archived_at?->format('M d, Y h:i A') ?? '—',
                ]),
            ],
        };

        return $this->downloads->download('Archived '.$details['label'], $headers, $rows, 'Archived records only');
    }

    private function details(string $type): array
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return self::TYPES[$type];
    }

    private function records(string $type, bool $archived = false): Builder
    {
        $this->details($type);

        $query = match ($type) {
            'attendance' => $archived ? AttendanceRecord::onlyArchived() : AttendanceRecord::query(),
            'system-logs' => $archived ? AuditLog::onlyArchived() : AuditLog::query(),
            'notifications' => $archived ? Announcement::onlyArchived() : Announcement::query(),
        };

        return $type === 'notifications' ? $query->where('status', 'published') : $query;
    }

    private function recentArchives(): Collection
    {
        $attendance = AttendanceRecord::onlyArchived()
            ->with(['student', 'attendanceSession.section.component'])
            ->latest('archived_at')->limit(8)->get()
            ->map(fn (AttendanceRecord $record) => [
                'type' => 'Attendance',
                'title' => $record->student?->name ?? 'Unknown student',
                'detail' => ($record->attendanceSession?->title ?? 'Unknown session').' · '.strtoupper($record->status),
                'archived_at' => $record->archived_at,
            ]);

        $logs = AuditLog::onlyArchived()->latest('archived_at')->limit(8)->get()
            ->map(fn (AuditLog $log) => [
                'type' => 'System log',
                'title' => $log->actor_name,
                'detail' => $log->description,
                'archived_at' => $log->archived_at,
            ]);

        $notifications = Announcement::onlyArchived()->where('status', 'published')->latest('archived_at')->limit(8)->get()
            ->map(fn (Announcement $announcement) => [
                'type' => 'Notification',
                'title' => $announcement->title,
                'detail' => $announcement->audienceLabel(),
                'archived_at' => $announcement->archived_at,
            ]);

        return $attendance->concat($logs)->concat($notifications)
            ->sortByDesc('archived_at')->take(12)->values();
    }
}
