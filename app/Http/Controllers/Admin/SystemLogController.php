<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SystemLogController extends Controller
{
    public function index(Request $request): View
    {
        $availableActions = AuditLog::query()->distinct()->orderBy('action')->pluck('action');
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(array_keys(User::ROLE_LABELS))],
            'action' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', Rule::in(['success', 'error'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $logs = AuditLog::query()
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('actor_name', 'like', "%{$search}%")
                        ->orWhere('actor_email', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('route_name', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%");
                });
            })
            ->when($filters['role'] ?? null, fn ($query, string $role) => $query->where('actor_role', $role))
            ->when($filters['action'] ?? null, fn ($query, string $action) => $query->where('action', $action))
            ->when(($filters['status'] ?? null) === 'success', fn ($query) => $query->where('status_code', '<', 400))
            ->when(($filters['status'] ?? null) === 'error', fn ($query) => $query->where('status_code', '>=', 400))
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        $metrics = [
            'today' => AuditLog::whereDate('created_at', today())->count(),
            'active_users' => AuditLog::where('created_at', '>=', now()->subDay())->distinct('user_id')->count('user_id'),
            'changes' => AuditLog::whereIn('action', ['create', 'update', 'delete', 'close', 'qr_scan'])->whereDate('created_at', today())->count(),
            'errors' => AuditLog::where('status_code', '>=', 400)->whereDate('created_at', today())->count(),
        ];

        return view('admin.system-logs.index', [
            'logs' => $logs,
            'filters' => $filters,
            'metrics' => $metrics,
            'roles' => User::ROLE_LABELS,
            'availableActions' => $availableActions,
        ]);
    }
}
