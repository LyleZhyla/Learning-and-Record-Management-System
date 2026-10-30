@extends('layouts.admin')
@section('title', $institution->exists ? 'Manage Institution' : 'Add Institution')
@section('page-title', $institution->exists ? 'Manage Institution' : 'Add Institution')
@section('content')
<div class="page-actions"><div><h2>{{ $institution->exists ? $institution->name : 'Create an isolated institution workspace' }}</h2><p>New institutions receive their own roles, components, policies, workflows, notifications, branding, and academic term.</p></div><a class="secondary-button" href="{{ route('admin.institutions.index') }}">Back to institutions</a></div>
<section class="card"><form method="POST" action="{{ $institution->exists ? route('admin.institutions.update', $institution) : route('admin.institutions.store') }}" class="settings-form">@csrf @if($institution->exists)@method('PUT')@endif
<div class="form-grid">
<label class="field-group full"><span>Official institution name</span><input name="name" value="{{ old('name', $institution->name) }}" maxlength="180" required></label>
<label class="field-group"><span>Short name</span><input name="short_name" value="{{ old('short_name', $institution->short_name) }}" maxlength="50" required></label>
<label class="field-group"><span>Institution code</span><input name="code" value="{{ old('code', $institution->code) }}" maxlength="30" required></label>
<label class="field-group"><span>URL slug</span><input name="slug" value="{{ old('slug', $institution->slug) }}" maxlength="120" placeholder="Generated from name"></label>
<label class="field-group"><span>Email</span><input type="email" name="email" value="{{ old('email', $institution->email) }}"></label>
<label class="field-group"><span>Phone</span><input name="phone" value="{{ old('phone', $institution->phone) }}" maxlength="50"></label>
<label class="field-group full"><span>Website</span><input type="url" name="website" value="{{ old('website', $institution->website) }}"></label>
<label class="field-group full"><span>Address</span><textarea name="address" rows="3">{{ old('address', $institution->address) }}</textarea></label>
<label class="field-group"><span>Status</span><select name="is_active"><option value="1" @selected(old('is_active', $institution->exists ? $institution->is_active : true))>Active</option><option value="0" @selected(!old('is_active', $institution->exists ? $institution->is_active : true))>Inactive</option></select></label>
</div><div class="form-actions"><button class="primary-button" type="submit">{{ $institution->exists ? 'Save institution' : 'Create institution' }}</button></div></form></section>
@if($institution->exists)
<section class="card"><div class="card-heading"><div><span class="eyebrow">Campus directory</span><h3>Campuses</h3><p>Campuses remain inside this institution's tenant boundary.</p></div></div>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Name</th><th>Code</th><th>Address</th><th>Type</th><th>Status</th></tr></thead><tbody>@foreach($institution->campuses as $campus)<tr><td><strong>{{ $campus->name }}</strong></td><td>{{ $campus->code }}</td><td>{{ $campus->address ?: '—' }}</td><td>{{ $campus->is_main ? 'Main campus' : 'Extension' }}</td><td>{{ $campus->is_active ? 'Active' : 'Inactive' }}</td></tr>@endforeach</tbody></table></div>
<form method="POST" action="{{ route('admin.institutions.campuses.store', $institution) }}" class="settings-form">@csrf<div class="form-grid"><label class="field-group"><span>Campus name</span><input name="name" required></label><label class="field-group"><span>Code</span><input name="code" maxlength="40" required></label><label class="field-group full"><span>Address</span><textarea name="address" rows="2"></textarea></label><label class="field-group"><span><input type="checkbox" name="is_main" value="1"> Set as main campus</span></label></div><div class="form-actions"><button class="primary-button compact" type="submit">Add campus</button></div></form></section>
@endif
@endsection
