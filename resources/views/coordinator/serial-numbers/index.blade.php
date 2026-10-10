@extends('layouts.coordinator')
@section('title', 'NSTP Serial Numbers')
@section('page-title', 'NSTP Serial Numbers')
@section('content')
<section class="welcome-banner">
    <div><span class="eyebrow">CHED-provided records</span><h2>Receive and encode graduate serial numbers</h2><p>Upload the official file received by the office, then encode only the serial numbers shown in that document. The system never generates serial numbers.</p></div>
    <a class="secondary-button" href="{{ route('serial-numbers.verify') }}" target="_blank" rel="noopener">Open public verifier</a>
</section>

<div class="dashboard-grid two-column" style="margin-top:1.25rem;align-items:start">
    <section class="card">
        <div class="card-heading"><div><span class="eyebrow">New release batch</span><h3>Upload the official serial-number file</h3><p>{{ $component?->code ?? 'No assigned component' }} records only. PDF, Excel, CSV, or image files up to 10 MB are accepted.</p></div></div>
        <form method="POST" action="{{ route('coordinator.serial-numbers.store') }}" enctype="multipart/form-data" class="stack-form">
            @csrf
            <div class="form-grid"><label class="field-group"><span>Academic year</span><input name="academic_year" value="{{ old('academic_year', \App\Models\SystemSetting::studentRegistrationAcademicYear()) }}" pattern="\d{4}-\d{4}" required></label><label class="field-group"><span>Semester</span><select name="semester" required>@foreach($semesters as $value => $label)<option value="{{ $value }}" @selected(old('semester', \App\Models\SystemSetting::studentRegistrationSemester())===$value)>{{ $label }}</option>@endforeach</select></label></div>
            <label class="field-group"><span>Date received by the office</span><input type="date" name="received_at" value="{{ old('received_at', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required></label>
            <label class="field-group"><span>Official file from CHED/issuing office</span><input type="file" name="source_file" accept=".pdf,.xlsx,.xls,.csv,.jpg,.jpeg,.png" required><small class="form-help">This file is private and downloadable only inside the Coordinator portal.</small></label>
            <label class="field-group"><span>Notes (optional)</span><textarea name="notes" rows="3" maxlength="3000" placeholder="Reference, coverage, or instructions included with the file">{{ old('notes') }}</textarea></label>
            <button class="primary-button" type="submit">Upload official file and start encoding</button>
        </form>
    </section>

    <section class="card user-table-card">
        <div class="card-heading"><div><span class="eyebrow">Received files</span><h3>{{ $component?->code }} serial-number releases</h3><p>Open a batch to encode or review graduate serial numbers.</p></div><span class="pill">{{ $releases->total() }} batch{{ $releases->total() === 1 ? '' : 'es' }}</span></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Term</th><th>Received</th><th>File</th><th>Encoded</th><th></th></tr></thead><tbody>
        @forelse($releases as $release)
            <tr><td><strong>{{ $release->academic_year }}</strong><br><small>{{ $semesters[$release->semester] ?? str($release->semester)->headline() }}</small></td><td>{{ $release->received_at->format('M d, Y') }}</td><td>{{ Str::limit($release->source_file_original_name, 28) }}</td><td>{{ $release->serial_numbers_count }}</td><td><a class="table-action" href="{{ route('coordinator.serial-numbers.show', $release) }}">Open batch</a></td></tr>
        @empty
            <tr><td colspan="5"><div class="empty-state"><strong>No official file uploaded yet</strong><span>Upload the list received by the office to begin encoding.</span></div></td></tr>
        @endforelse
        </tbody></table></div>
        @if($releases->hasPages())<div class="pagination-row">{{ $releases->links() }}</div>@endif
    </section>
</div>
@endsection
