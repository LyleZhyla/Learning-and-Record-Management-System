@extends('layouts.admin')

@section('title', $role ? 'Edit Role' : 'Create Role')
@section('page-title', $role ? 'Edit Role' : 'Create Custom Role')

@section('content')
<div class="back-row"><a href="{{ route('admin.roles.index') }}">← Back to roles and permissions</a></div>

<form method="POST" action="{{ $role ? route('admin.roles.update', $role) : route('admin.roles.store') }}" class="role-editor" data-role-editor>
    @csrf
    @if($role) @method('PUT') @endif

    <section class="card">
        <div class="card-heading"><div><span class="eyebrow">Role identity</span><h3>{{ $role ? $role->name : 'New custom role' }}</h3><p>The base portal controls the account's data scope. Permissions control which features inside that portal are available.</p></div></div>
        <div class="form-grid">
            <label class="field-group"><span>Role name</span><input name="name" value="{{ old('name', $role?->name) }}" maxlength="100" required>@error('name')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label class="field-group"><span>Base portal</span>
                <select name="base_role" required data-role-base @disabled($role)>
                    @foreach($baseRoles as $value => $label)<option value="{{ $value }}" @selected(old('base_role', $role?->base_role ?? 'facilitator') === $value)>{{ $label }}</option>@endforeach
                </select>
                @if($role)<input type="hidden" name="base_role" value="{{ $role->base_role }}">@endif
                <small class="form-help">Cannot be changed after the role is created.</small>
            </label>
            <label class="field-group full"><span>Description</span><textarea name="description" rows="3" maxlength="500">{{ old('description', $role?->description) }}</textarea></label>
            <label class="field-group"><span>Status</span><select name="is_active" required><option value="1" @selected(old('is_active', $role?->is_active ?? true))>Active</option><option value="0" @selected(!old('is_active', $role?->is_active ?? true))>Inactive</option></select></label>
        </div>
    </section>

    <section class="card role-permission-card">
        <div class="card-heading"><div><span class="eyebrow">Authorization</span><h3>Permission matrix</h3><p>Unchecked features return a 403 response even if their URL is entered directly.</p></div><button class="secondary-outline-button compact" type="button" data-toggle-permissions>Select visible</button></div>
        @php($selectedPermissions = collect(old('permissions', $role?->permissions ?? [])))
        <div class="permission-groups">
            @foreach($permissionGroups as $group => $permissions)
                <fieldset class="permission-group"><legend>{{ $group }}</legend>
                    @foreach($permissions as $key => $definition)
                        <label class="permission-option" data-permission-bases='@json($definition["bases"])'>
                            <input type="checkbox" name="permissions[]" value="{{ $key }}" @checked($selectedPermissions->contains($key))>
                            <span><strong>{{ $definition['label'] }}</strong><small>{{ $key }}</small></span>
                        </label>
                    @endforeach
                </fieldset>
            @endforeach
        </div>
        @error('permissions')<small class="field-error">{{ $message }}</small>@enderror
        @error('permissions.*')<small class="field-error">{{ $message }}</small>@enderror
    </section>

    <div class="form-actions split-actions">
        @if($role && !$role->is_system)
            <button class="danger-button" type="submit" form="delete-role-form">Delete role</button>
        @else<span></span>@endif
        <div><a class="cancel-button" href="{{ route('admin.roles.index') }}">Cancel</a> <button class="primary-button compact" type="submit">{{ $role ? 'Save permission matrix' : 'Create role' }}</button></div>
    </div>
</form>

@if($role && !$role->is_system)
<form id="delete-role-form" method="POST" action="{{ route('admin.roles.destroy', $role) }}" onsubmit="return confirm('Delete this custom role? Accounts must be reassigned first.');">@csrf @method('DELETE')</form>
@endif

<script src="{{ asset('js/role-editor.js') }}?v={{ filemtime(public_path('js/role-editor.js')) }}"></script>
@endsection
