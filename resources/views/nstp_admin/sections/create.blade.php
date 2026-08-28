@extends($routePrefix === 'admin' ? 'layouts.admin' : 'layouts.nstp-admin')
@section('title','Create Section')
@section('page-title','Create NSTP Section')
@section('content')
<div class="back-row"><a href="{{ route($routePrefix.'.sections.index') }}">← Back to sections</a></div>
<section class="card section-form-card"><div class="card-heading"><div><span class="eyebrow">New section</span><h3>Section information</h3><p>Set the component, term, capacity, and optional facilitator assignment.</p></div></div><form method="POST" action="{{ route($routePrefix.'.sections.store') }}" class="account-form">@csrf @include('nstp_admin.sections._form')<div class="form-actions split-actions"><a class="cancel-button" href="{{ route($routePrefix.'.sections.index') }}">Cancel</a><button class="primary-button compact" type="submit">Create section</button></div></form></section>
@endsection
