@extends('layouts.coordinator')
@section('title', 'Encode Serial Numbers')
@section('page-title', 'Encode Serial Numbers')
@section('content')
<div class="page-actions"><div><span class="eyebrow">{{ $serialNumberRelease->component->code }} · {{ $serialNumberRelease->academic_year }}</span><h2>{{ \App\Models\NstpSection::SEMESTERS[$serialNumberRelease->semester] ?? str($serialNumberRelease->semester)->headline() }} graduate serials</h2><p>Encode each value exactly as printed in the official uploaded file.</p></div><div><a class="secondary-outline-button" href="{{ route('coordinator.serial-numbers.download', $serialNumberRelease) }}">Download official file</a> <a class="secondary-button" href="{{ route('coordinator.serial-numbers.index') }}">Back to batches</a></div></div>

<section class="card" style="margin-bottom:1.25rem">
    <div class="card-heading"><div><h3>Source record</h3><p>The uploaded document is retained as evidence for every serial number in this batch.</p></div><span class="status-badge active"><i></i>Official file received</span></div>
    <dl class="visible-data-grid"><div><dt>Filename</dt><dd>{{ $serialNumberRelease->source_file_original_name }}</dd></div><div><dt>Date received</dt><dd>{{ $serialNumberRelease->received_at->format('M d, Y') }}</dd></div><div><dt>Uploaded by</dt><dd>{{ $serialNumberRelease->uploader?->name ?? 'Former coordinator' }}</dd></div><div><dt>Encoded</dt><dd>{{ $encoded->count() }} / {{ $graduates->count() }} qualified graduates</dd></div></dl>
    @if($serialNumberRelease->notes)<p>{{ $serialNumberRelease->notes }}</p>@endif
</section>

<section class="card user-table-card">
    <div class="card-heading"><div><span class="eyebrow">Qualified graduates</span><h3>CHED serial-number encoding</h3><p>Only fully graded students who reached the section passing percentage appear here.</p></div></div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Graduate</th><th>Section</th><th>Current serial number</th><th>Encode / correct</th></tr></thead><tbody>
    @forelse($graduates as $enrollment)
        @php($record = $encoded->get($enrollment->id))
        <tr>
            <td><strong>{{ $enrollment->student->name }}</strong><br><small class="muted-cell">{{ $enrollment->student->studentProfile?->student_number ?? $enrollment->student->email }}</small></td>
            <td>{{ $enrollment->section?->code }}</td>
            <td>@if($record)<span class="status-badge active"><i></i>{{ $record->serial_number }}</span>@else<span class="status-badge pending"><i></i>Not encoded</span>@endif</td>
            <td><form method="POST" action="{{ route('coordinator.serial-numbers.students.store', [$serialNumberRelease, $enrollment]) }}" style="display:flex;gap:.5rem;min-width:24rem">@csrf @method('PUT')<input name="serial_number" value="{{ old('serial_number', $record?->serial_number) }}" maxlength="100" placeholder="Enter the CHED-provided serial" required style="flex:1"><button class="primary-button compact" type="submit">{{ $record ? 'Update' : 'Save' }}</button></form></td>
        </tr>
    @empty
        <tr><td colspan="4"><div class="empty-state"><strong>No qualified graduates found</strong><span>Complete all assessment grading for this component and term before encoding serial numbers.</span></div></td></tr>
    @endforelse
    </tbody></table></div>
</section>
@endsection
