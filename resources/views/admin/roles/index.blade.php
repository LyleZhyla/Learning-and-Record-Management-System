@extends('layouts.admin')

@section('title', 'Roles & Permissions')
@section('page-title', 'Roles & Permissions')

@section('content')
<section class="page-actions">
    <div><span class="eyebrow">Access control</span><h2>Role and permission matrix</h2><p>Define what each account role can access while keeping its assigned portal and data scope.</p></div>
    <a class="primary-button compact" href="{{ route('admin.roles.create') }}">Create custom role</a>
</section>

<section class="card user-table-card">
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Role</th><th>Base portal</th><th>Permissions</th><th>Accounts</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            @foreach($roles as $role)
                <tr>
                    <td><strong>{{ $role->name }}</strong><br><small class="muted-cell">{{ $role->description ?: 'No description' }}</small></td>
                    <td>{{ \App\Models\User::ROLE_LABELS[$role->base_role] ?? str($role->base_role)->headline() }} @if($role->is_system)<span class="pill">Built-in</span>@endif</td>
                    <td>{{ in_array('*', $role->permissions ?? [], true) ? 'Full system access' : count($role->permissions ?? []).' enabled' }}</td>
                    <td>{{ $role->users_count }}</td>
                    <td><span class="status-badge {{ $role->is_active ? 'active' : 'inactive' }}"><i></i>{{ $role->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td>
                        @if($role->base_role === 'super_admin')
                            <span class="muted-cell">Protected</span>
                        @else
                            <a class="table-action" href="{{ route('admin.roles.edit', $role) }}">Edit matrix</a>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>
@endsection
