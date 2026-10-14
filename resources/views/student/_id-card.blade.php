@php($branding = \App\Models\SystemSetting::branding())
@php($idDesign = \App\Models\SystemSetting::studentIdDesign())
<article class="student-id-card" aria-label="Auto-generated student identification card" style="--id-primary:{{ $idDesign['primary_color'] }};--id-secondary:{{ $idDesign['secondary_color'] }};--id-accent:{{ $idDesign['accent_color'] }}">
    <div class="student-id-accent"></div>
    <div class="student-id-watermark" aria-hidden="true">{{ $branding['program_short_name'] }}</div>
    <header class="student-id-header">
        <span class="student-id-institution-logos"><img src="{{ \App\Models\SystemSetting::brandingLogoUrl('organization') }}" alt="{{ $branding['organization_short_name'] }} logo"><img src="{{ \App\Models\SystemSetting::brandingLogoUrl('program') }}" alt="{{ $branding['program_short_name'] }} logo"></span>
        <div><strong>{{ $branding['organization_name'] }}</strong><span>{{ $branding['program_name'] }}</span></div>
        <b>{{ $idDesign['title'] }}</b>
    </header>

    <div class="student-id-body">
        <div class="student-id-photo">
            @if($student->profile_photo_path)
                <img src="{{ route('profile.photo', ['v' => $student->updated_at?->timestamp]) }}" alt="Profile photo of {{ $displayName }}">
            @else
                <span>{{ strtoupper(substr($displayName, 0, 1)) }}</span>
            @endif
            <small>OFFICIAL STUDENT</small>
        </div>

        <div class="student-id-information">
            <span class="student-id-label">Official student record</span>
            <h3>{{ $displayName }}</h3>
            <div class="student-id-number-line"><span>Student number</span><strong>{{ $details?->student_number ?: 'Not provided' }}</strong></div>
            <div class="student-id-fields">
                @if($idDesign['show_component'])<div><span>NSTP component</span><strong>{{ $enrollment?->component?->code ?: 'Not assigned' }}</strong></div>@endif
                <div><span>Academic year</span><strong>{{ $enrollment?->academic_year ?: now()->format('Y') }}</strong></div>
                @if($idDesign['show_course'])<div class="wide"><span>Course</span><strong>{{ $details?->course ?: 'Not provided' }}</strong></div>@endif
                @if($idDesign['show_emergency_contact'])<div class="wide"><span>Emergency contact</span><strong>{{ $details?->emergency_contact_name ?: 'Not provided' }}@if($details?->emergency_relationship) · {{ $details->emergency_relationship }}@endif</strong><small>{{ $details?->emergency_contact_number ?: 'Contact number not provided' }}</small></div>@endif
            </div>
        </div>

        <div class="student-id-qr">
            {!! $qrSvg !!}
            <strong>SCAN FOR ATTENDANCE</strong>
            <small>Permanent student QR</small>
        </div>
    </div>

    <footer class="student-id-footer"><span>{{ $idDesign['footer_text'] }}</span><strong>{{ $branding['organization_short_name'] }} · {{ $branding['program_short_name'] }}</strong></footer>
</article>
