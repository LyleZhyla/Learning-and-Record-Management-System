@extends('layouts.coordinator')
@section('title', 'Review MS-1 Proof')
@section('page-title', 'Review MS-1 Proof')

@section('content')
<div class="back-row"><a href="{{ route('coordinator.rotc-approvals.index') }}">← Back to ROTC approvals</a></div>

<section class="page-actions proof-review-heading">
    <div><span class="eyebrow">{{ $enrollment->rotc_category }} request</span><h2>{{ $enrollment->student->name }}</h2><p>Review the submitted MS-1 completion proof before approving this request.</p></div>
    <a class="secondary-outline-button" href="{{ route('coordinator.rotc-approvals.proof.download', $enrollment) }}">↓ Download proof</a>
</section>

<div class="proof-review-layout">
    <section class="card proof-preview-card">
        <div class="card-heading"><div><h3>MS-1 completion proof</h3><p>{{ $enrollment->rotc_proof_original_name }}</p></div></div>
        <iframe class="proof-preview-frame" src="{{ route('coordinator.rotc-approvals.proof.file', $enrollment) }}" title="MS-1 completion proof submitted by {{ $enrollment->student->name }}"></iframe>
    </section>

    <aside class="card proof-request-summary">
        <div class="card-heading"><div><span class="eyebrow">Request details</span><h3>Student information</h3></div></div>
        <dl>
            <div><dt>Name</dt><dd>{{ $enrollment->student->name }}</dd></div>
            <div><dt>Email</dt><dd>{{ $enrollment->student->email }}</dd></div>
            <div><dt>Requested category</dt><dd>{{ $enrollment->rotc_category }}</dd></div>
            <div><dt>Shirt size</dt><dd>{{ $enrollment->shirt_size }}</dd></div>
            <div><dt>Term</dt><dd>{{ ucfirst($enrollment->semester) }} {{ $enrollment->academic_year }}</dd></div>
        </dl>
        <form method="POST" action="{{ route('coordinator.rotc-approvals.approve', $enrollment) }}">
            @csrf
            @method('PATCH')
            <button class="success-button proof-approve-button" type="submit">Approve {{ $enrollment->rotc_category }}</button>
        </form>
    </aside>
</div>
@endsection
