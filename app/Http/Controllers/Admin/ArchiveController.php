<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\StudentRegistration;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Services\SpreadsheetDownloadService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArchiveController extends Controller
{
    private const TYPES = [
        'attendance' => ['label' => 'Attendance records', 'description' => 'Student attendance entries from all sessions and components.', 'icon' => '▣'],
        'system-logs' => ['label' => 'System logs', 'description' => 'Authenticated activity and security audit trail entries.', 'icon' => '☷'],
        'notifications' => ['label' => 'Notifications', 'description' => 'Published announcements currently shown in notification bells.', 'icon' => '🔔'],
    ];

    private const BULK_DELETE_TARGETS = [
        'student-accounts' => ['label' => 'Student accounts', 'description' => 'All student accounts and their linked enrollments, attendance, submissions, messages, and AI learning recommendations.'],
        'staff-accounts' => ['label' => 'Facilitator and coordinator accounts', 'description' => 'All facilitator and coordinator accounts. Institutional content is reassigned to the acting Super Admin.'],
        'archived-registrations' => ['label' => 'Archived registrations', 'description' => 'Archived public registration records and their uploaded registration files.'],
        'archived-attendance' => ['label' => 'Archived attendance records', 'description' => 'Attendance entries already moved to the archive.'],
        'archived-system-logs' => ['label' => 'Archived system logs', 'description' => 'Audit trail entries already moved to the archive.'],
        'archived-notifications' => ['label' => 'Archived notifications', 'description' => 'Published announcements already moved to the archive.'],
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
            'bulkDeleteTargets' => collect(self::BULK_DELETE_TARGETS)->map(fn (array $details, string $target): array => $details + [
                'target' => $target,
                'count' => $this->bulkDeleteCount($target),
            ]),
            'allowRestore' => WorkflowDefinition::ruleEnabled('archiving', 'allow_restore'),
            'requireDeleteConfirmation' => WorkflowDefinition::ruleEnabled('archiving', 'require_delete_confirmation'),
        ]);
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $rules = [
            'targets' => ['required', 'array', 'min:1'],
            'targets.*' => ['required', 'distinct', Rule::in(array_keys(self::BULK_DELETE_TARGETS))],
        ];
        if (WorkflowDefinition::ruleEnabled('archiving', 'require_delete_confirmation')) {
            $rules['confirmation'] = ['required', Rule::in(['DELETE SELECTED'])];
        }
        $validated = $request->validate($rules, [
            'targets.required' => 'Select at least one record category to delete.',
            'confirmation.required' => 'Type DELETE SELECTED to confirm permanent deletion.',
            'confirmation.in' => 'Type DELETE SELECTED exactly to confirm permanent deletion.',
        ]);

        $filePaths = collect();
        $deleted = [];
        $actor = $request->user();

        DB::transaction(function () use ($validated, $actor, $filePaths, &$deleted): void {
            foreach ($validated['targets'] as $target) {
                $deleted[$target] = match ($target) {
                    'student-accounts' => $this->deleteStudentAccounts($filePaths),
                    'staff-accounts' => $this->deleteStaffAccounts($actor, $filePaths),
                    'archived-registrations' => $this->deleteArchivedRegistrations($filePaths),
                    'archived-attendance' => AttendanceRecord::onlyArchived()->delete(),
                    'archived-system-logs' => AuditLog::onlyArchived()->delete(),
                    'archived-notifications' => Announcement::onlyArchived()->where('status', 'published')->delete(),
                };
            }
        });

        if ($filePaths->isNotEmpty()) {
            Storage::disk('local')->delete($filePaths->filter()->unique()->values()->all());
        }

        $summary = collect($deleted)
            ->map(fn (int $count, string $target): string => number_format($count).' '.self::BULK_DELETE_TARGETS[$target]['label'])
            ->implode(', ');

        return back()->with('status', 'Bulk deletion completed: '.$summary.'.');
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
        abort_unless(WorkflowDefinition::ruleEnabled('archiving', 'allow_restore'), 409, 'Restoring archived records is disabled in Workflow Rules.');
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
        if (WorkflowDefinition::ruleEnabled('archiving', 'require_delete_confirmation')) {
            $request->validate([
                'confirmation' => ['required', 'in:DELETE'],
            ], [
                'confirmation.in' => 'Type DELETE exactly to permanently remove the archived records.',
                'confirmation.required' => 'Type DELETE to confirm permanent deletion.',
            ]);
        }

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

    private function bulkDeleteCount(string $target): int
    {
        return match ($target) {
            'student-accounts' => User::where('role', 'student')->count(),
            'staff-accounts' => User::whereIn('role', ['facilitator', 'coordinator'])->count(),
            'archived-registrations' => StudentRegistration::whereNotNull('archived_at')->count(),
            'archived-attendance' => AttendanceRecord::onlyArchived()->count(),
            'archived-system-logs' => AuditLog::onlyArchived()->count(),
            'archived-notifications' => Announcement::onlyArchived()->where('status', 'published')->count(),
        };
    }

    private function deleteStudentAccounts(Collection $filePaths): int
    {
        $students = User::with('studentProfile')->where('role', 'student')->get();
        $filePaths->push(...$students->flatMap(fn (User $student): array => [
            $student->profile_photo_path,
            $student->studentProfile?->cor_path,
            $student->studentProfile?->formal_photo_path,
        ])->filter()->all());

        return User::whereKey($students->modelKeys())->delete();
    }

    private function deleteStaffAccounts(User $actor, Collection $filePaths): int
    {
        $staff = User::whereIn('role', ['facilitator', 'coordinator'])->get();
        $ids = $staff->modelKeys();
        $filePaths->push(...$staff->pluck('profile_photo_path')->filter()->all());

        if ($ids === []) {
            return 0;
        }

        DB::table('attendance_sessions')->whereIn('created_by', $ids)->update(['created_by' => $actor->id]);
        DB::table('learning_materials')->whereIn('created_by', $ids)->update(['created_by' => $actor->id]);
        DB::table('assessments')->whereIn('created_by', $ids)->update(['created_by' => $actor->id]);
        DB::table('omr_sheets')->whereIn('created_by', $ids)->update(['created_by' => $actor->id]);
        DB::table('omr_scan_results')->whereIn('scanned_by', $ids)->update(['scanned_by' => $actor->id]);
        DB::table('sessions')->whereIn('user_id', $ids)->delete();

        return User::whereKey($ids)->delete();
    }

    private function deleteArchivedRegistrations(Collection $filePaths): int
    {
        $registrations = StudentRegistration::whereNotNull('archived_at')->get();
        $filePaths->push(...$registrations->flatMap(fn (StudentRegistration $registration): array => [
            $registration->cor_path,
            $registration->formal_photo_path,
        ])->filter()->all());

        return StudentRegistration::whereKey($registrations->modelKeys())->delete();
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
