@extends('layouts.admin')
@section('title', 'Institutions')
@section('page-title', 'Institutions & Campuses')
@section('content')
<div class="page-actions"><div><h2>Multi-institution management</h2><p>Each institution has isolated accounts, program records, roles, configuration, and academic terms.</p></div><a class="primary-button" href="{{ route('admin.institutions.create') }}">+ Add institution</a></div>
<section class="metric-grid">
    <article class="metric-card"><div><small>Institutions</small><strong>{{ $institutions->count() }}</strong><p>{{ $institutions->where('is_active', true)->count() }} active</p></div></article>
    <article class="metric-card"><div><small>Campuses</small><strong>{{ $institutions->sum('campuses_count') }}</strong><p>Across all institutions</p></div></article>
    <article class="metric-card"><div><small>Tenant accounts</small><strong>{{ $institutions->sum('users_count') }}</strong><p>System administrators excluded</p></div></article>
</section>
<section class="card user-table-card"><div class="table-wrap"><table class="data-table"><thead><tr><th>Institution</th><th>Code</th><th>Campuses</th><th>Accounts</th><th>Status</th><th>Context</th><th></th></tr></thead><tbody>
@foreach($institutions as $institution)<tr><td><strong>{{ $institution->name }}</strong><br><small>{{ $institution->address ?: 'No address recorded' }}</small></td><td>{{ $institution->code }}</td><td>{{ $institution->campuses_count }}</td><td>{{ $institution->users_count }}</td><td><span class="status-badge {{ $institution->is_active ? 'active' : 'inactive' }}"><i></i>{{ $institution->is_active ? 'Active' : 'Inactive' }}</span></td><td>@if($activeInstitutionId === $institution->id)<span class="pill">Current</span>@elseif($institution->is_active)<form method="POST" action="{{ route('admin.institutions.switch', $institution) }}">@csrf<button class="text-link" type="submit">Switch here</button></form>@endif</td><td><a class="secondary-outline-button compact" href="{{ route('admin.institutions.edit', $institution) }}">Manage</a></td></tr>
@endforeach</tbody></table></div></section>
@endsection
