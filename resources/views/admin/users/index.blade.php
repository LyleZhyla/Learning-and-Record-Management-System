@extends('layouts.admin')

@section('title', 'User Accounts')
@section('page-title', 'User and Role Management')

@section('content')
    <section class="page-actions">
        <div>
            <span class="eyebrow">Account administration</span>
            <h2>Manage system users</h2>
            <p>Create accounts, assign roles, monitor access, and control account status.</p>
        </div>
        <div class="page-action-buttons">
            <a class="secondary-outline-button" href="{{ route('admin.students.import.create') }}">Import students</a>
            <a class="primary-button compact" href="{{ route('admin.users.create') }}">+ Create account</a>
        </div>
    </section>

    @if ($errors->any())
        <div class="alert danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <section class="role-summary-grid" aria-label="Accounts by role">
        @foreach (\App\Models\User::ROLE_LABELS as $role => $label)
            <a class="role-summary {{ request('role') === $role ? 'selected' : '' }}" href="{{ route('admin.users.index', ['role' => $role]) }}">
                <span class="role-dot role-{{ $role }}"></span>
                <div><strong>{{ $roleCounts[$role] ?? 0 }}</strong><small>{{ $label }}</small></div>
            </a>
        @endforeach
    </section>

    <section class="card user-table-card">
        <form class="filter-bar" method="GET" action="{{ route('admin.users.index') }}">
            <label class="search-field">
                <span aria-hidden="true">⌕</span>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name or email">
            </label>
            <select name="role" aria-label="Filter by role">
                <option value="">All roles</option>
                @foreach (\App\Models\User::ROLE_LABELS as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['role'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="status" aria-label="Filter by status">
                <option value="">All statuses</option>
                @foreach (\App\Models\User::STATUS_LABELS as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="filter-button" type="submit">Apply filters</button>
            @if (request()->hasAny(['search', 'role', 'status']))
                <a class="clear-filter" href="{{ route('admin.users.index') }}">Clear</a>
            @endif
        </form>

        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>User</th><th>Role</th><th>Status</th><th>Last sign in</th><th class="align-right">Action</th></tr></thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <span class="table-avatar">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                                    <div><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></div>
                                </div>
                            </td>
                            <td><span class="role-badge role-{{ $user->role }}">{{ $user->roleLabel() }}</span></td>
                            <td><span class="status-badge {{ $user->status }}"><i></i>{{ $user->statusLabel() }}</span></td>
                            <td class="muted-cell">{{ $user->last_login_at?->format('M d, Y · h:i A') ?? 'Never' }}</td>
                            <td class="align-right"><div class="account-row-actions"><a class="table-action" href="{{ route('admin.users.edit', $user) }}">Edit</a>@unless(auth()->user()->is($user))<form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Permanently delete {{ addslashes($user->name) }}? Their login and personal records will be removed. This cannot be undone.')">@csrf @method('DELETE')<button class="link-danger" type="submit">Delete</button></form>@endunless</div></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty-state"><strong>No accounts found</strong><span>Try changing the filters or create a new account.</span></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="pagination-row">
                <span>Showing {{ $users->firstItem() }}–{{ $users->lastItem() }} of {{ $users->total() }}</span>
                <div>
                    @if ($users->onFirstPage()) <span class="page-button disabled">Previous</span> @else <a class="page-button" href="{{ $users->previousPageUrl() }}">Previous</a> @endif
                    <span class="page-current">Page {{ $users->currentPage() }} of {{ $users->lastPage() }}</span>
                    @if ($users->hasMorePages()) <a class="page-button" href="{{ $users->nextPageUrl() }}">Next</a> @else <span class="page-button disabled">Next</span> @endif
                </div>
            </div>
        @endif
    </section>
@endsection
