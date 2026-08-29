@extends('layouts.nstp-admin')
@section('title', 'New Announcement') @section('page-title', 'New Announcement')
@section('content')
<div class="back-row"><a href="{{ route('nstp_admin.announcements.index') }}">← Back to announcements</a></div><section class="card form-card"><div class="card-heading"><div><span class="eyebrow">Communication center</span><h2>Create announcement</h2><p>Save as draft or publish immediately.</p></div></div><form method="POST" action="{{ route('nstp_admin.announcements.store') }}">@csrf @include('nstp_admin.announcements._form', ['announcement' => null])<div class="form-actions"><a class="secondary-outline-button" href="{{ route('nstp_admin.announcements.index') }}">Cancel</a><button class="primary-button">Save announcement</button></div></form></section>
@endsection
