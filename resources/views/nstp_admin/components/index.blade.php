@extends($routePrefix === 'admin' ? 'layouts.admin' : 'layouts.nstp-admin')

@section('title', 'NSTP Components')
@section('page-title', 'NSTP Components')

@section('content')
    <section class="page-actions">
        <div><span class="eyebrow">Program configuration</span><h2>Configure CWTS, LTS, and ROTC</h2><p>Manage component availability, descriptions, and the default capacity used by automated sectioning.</p></div>
        <a class="primary-button compact" href="{{ route($routePrefix.'.sectioning.index') }}">Open automated sectioning</a>
    </section>

    @include('nstp_admin.components._cards', ['componentCards' => $components, 'componentReturnTo' => 'components'])
@endsection
