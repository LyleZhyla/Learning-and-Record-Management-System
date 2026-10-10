@extends($layout)
@section('title', 'Landing Page Editor')
@section('page-title', 'Landing Page Editor')

@section('content')
<style>
    .landing-editor-form{display:grid;gap:20px}.landing-editor-form .full-span{grid-column:1/-1}.landing-editor-form textarea{width:100%;resize:vertical;border:1px solid #dbe2ec;border-radius:10px;background:#fbfcfe;padding:12px 13px;color:var(--ink);font:inherit;line-height:1.55;outline:none}.landing-editor-form textarea:focus{border-color:#4f83d5;box-shadow:0 0 0 3px rgba(79,131,213,.1);background:white}html[data-theme=dark] .landing-editor-form textarea{border-color:#30405a;background:#111d2d;color:#edf4ff}@media(max-width:760px){.landing-editor-form .full-span{grid-column:auto}}
</style>
<div class="page-actions">
    <div><span class="eyebrow">Public website</span><h2>Edit landing page</h2><p>Update public-facing NSTP content and publish it immediately for website visitors.</p></div>
    <a class="secondary-outline-button" href="{{ route('landing', ['preview' => 1]) }}" target="_blank" rel="noopener">Preview landing page ↗</a>
</div>

<form class="landing-editor-form" method="POST" action="{{ route($routePrefix.'.landing-page.update') }}">
    @csrf
    @method('PUT')

    <section class="card form-card">
        <div class="card-heading"><div><span class="eyebrow">01 · Hero</span><h3>Opening message and media</h3><p>The video uses the poster image automatically whenever the remote video is unavailable.</p></div></div>
        <div class="form-grid">
            <label class="field-group"><span>Video URL</span><input type="url" name="hero_video_url" value="{{ old('hero_video_url', $landing['hero_video_url']) }}" required>@error('hero_video_url')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label class="field-group"><span>Poster image URL</span><input type="url" name="hero_poster_url" value="{{ old('hero_poster_url', $landing['hero_poster_url']) }}" required>@error('hero_poster_url')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label class="field-group"><span>Brand label</span><input name="hero_brand" value="{{ old('hero_brand', $landing['hero_brand']) }}" maxlength="180" required></label>
            <label class="field-group"><span>Headline line 1</span><input name="hero_line_1" value="{{ old('hero_line_1', $landing['hero_line_1']) }}" maxlength="180" required></label>
            <label class="field-group"><span>Headline line 2</span><input name="hero_line_2" value="{{ old('hero_line_2', $landing['hero_line_2']) }}" maxlength="180" required></label>
            <label class="field-group"><span>Headline line 3</span><input name="hero_line_3" value="{{ old('hero_line_3', $landing['hero_line_3']) }}" maxlength="180" required></label>
            <label class="field-group"><span>Supporting line 1</span><textarea name="hero_lead_1" rows="2" maxlength="2000" required>{{ old('hero_lead_1', $landing['hero_lead_1']) }}</textarea></label>
            <label class="field-group"><span>Supporting line 2</span><textarea name="hero_lead_2" rows="2" maxlength="2000" required>{{ old('hero_lead_2', $landing['hero_lead_2']) }}</textarea></label>
        </div>
    </section>

    <section class="card form-card">
        <div class="card-heading"><div><span class="eyebrow">02 · About</span><h3>Program introduction</h3></div></div>
        <div class="form-grid">
            <label class="field-group"><span>Section label</span><input name="about_eyebrow" value="{{ old('about_eyebrow', $landing['about_eyebrow']) }}" required></label>
            <label class="field-group"><span>Section heading</span><input name="about_title" value="{{ old('about_title', $landing['about_title']) }}" required></label>
            <label class="field-group full-span"><span>Lead statement</span><textarea name="about_lead" rows="3" maxlength="2000" required>{{ old('about_lead', $landing['about_lead']) }}</textarea></label>
            <label class="field-group full-span"><span>Program description</span><textarea name="about_body" rows="4" maxlength="2000" required>{{ old('about_body', $landing['about_body']) }}</textarea></label>
            <label class="field-group"><span>Legal reference</span><input name="about_law_title" value="{{ old('about_law_title', $landing['about_law_title']) }}" required></label>
            <label class="field-group"><span>Legal reference description</span><textarea name="about_law_body" rows="2" maxlength="2000" required>{{ old('about_law_body', $landing['about_law_body']) }}</textarea></label>
        </div>
    </section>

    <section class="card form-card">
        <div class="card-heading"><div><span class="eyebrow">03 · Components</span><h3>ROTC, CWTS, and LTS content</h3><p>Program lists and registration links remain protected system functions.</p></div></div>
        <div class="form-grid">
            <label class="field-group"><span>Section label</span><input name="components_eyebrow" value="{{ old('components_eyebrow', $landing['components_eyebrow']) }}" required></label>
            <label class="field-group"><span>Section heading</span><input name="components_title" value="{{ old('components_title', $landing['components_title']) }}" required></label>
            <label class="field-group full-span"><span>Section introduction</span><textarea name="components_intro" rows="2" maxlength="2000" required>{{ old('components_intro', $landing['components_intro']) }}</textarea></label>
            @foreach(['rotc' => 'ROTC', 'cwts' => 'CWTS', 'lts' => 'LTS'] as $code => $label)
                <label class="field-group"><span>{{ $label }} heading</span><input name="{{ $code }}_title" value="{{ old($code.'_title', $landing[$code.'_title']) }}" required></label>
                <label class="field-group"><span>{{ $label }} description</span><textarea name="{{ $code }}_body" rows="3" maxlength="2000" required>{{ old($code.'_body', $landing[$code.'_body']) }}</textarea></label>
            @endforeach
        </div>
    </section>

    <section class="card form-card">
        <div class="card-heading"><div><span class="eyebrow">04 · Activities and services</span><h3>Section introductions and SNAPIE prompt</h3><p>Published announcement records and functional service links continue to use their dedicated modules.</p></div></div>
        <div class="form-grid">
            <label class="field-group"><span>Activities label</span><input name="activities_eyebrow" value="{{ old('activities_eyebrow', $landing['activities_eyebrow']) }}" required></label>
            <label class="field-group"><span>Activities heading</span><input name="activities_title" value="{{ old('activities_title', $landing['activities_title']) }}" required></label>
            <label class="field-group full-span"><span>Activities introduction</span><textarea name="activities_intro" rows="2" maxlength="2000" required>{{ old('activities_intro', $landing['activities_intro']) }}</textarea></label>
            <label class="field-group"><span>Services label</span><input name="services_eyebrow" value="{{ old('services_eyebrow', $landing['services_eyebrow']) }}" required></label>
            <label class="field-group"><span>Services heading</span><input name="services_title" value="{{ old('services_title', $landing['services_title']) }}" required></label>
            <label class="field-group full-span"><span>Services introduction</span><textarea name="services_intro" rows="2" maxlength="2000" required>{{ old('services_intro', $landing['services_intro']) }}</textarea></label>
            <label class="field-group"><span>SNAPIE help heading</span><input name="snapie_title" value="{{ old('snapie_title', $landing['snapie_title']) }}" required></label>
            <label class="field-group"><span>SNAPIE help message</span><textarea name="snapie_body" rows="2" maxlength="2000" required>{{ old('snapie_body', $landing['snapie_body']) }}</textarea></label>
        </div>
    </section>

    <section class="card form-card">
        <div class="card-heading"><div><span class="eyebrow">05 · Student information</span><h3>Frequently asked questions</h3></div></div>
        <div class="form-grid">
            <label class="field-group"><span>Section label</span><input name="faq_eyebrow" value="{{ old('faq_eyebrow', $landing['faq_eyebrow']) }}" required></label>
            <label class="field-group"><span>Section heading</span><input name="faq_title" value="{{ old('faq_title', $landing['faq_title']) }}" required></label>
            <label class="field-group full-span"><span>Section introduction</span><textarea name="faq_intro" rows="2" maxlength="2000" required>{{ old('faq_intro', $landing['faq_intro']) }}</textarea></label>
            @for($faq = 1; $faq <= 5; $faq++)
                <label class="field-group"><span>Question {{ $faq }}</span><input name="faq_{{ $faq }}_question" value="{{ old('faq_'.$faq.'_question', $landing['faq_'.$faq.'_question']) }}" required></label>
                <label class="field-group"><span>Answer {{ $faq }}</span><textarea name="faq_{{ $faq }}_answer" rows="3" maxlength="2000" required>{{ old('faq_'.$faq.'_answer', $landing['faq_'.$faq.'_answer']) }}</textarea></label>
            @endfor
        </div>
    </section>

    <section class="card form-card">
        <div class="card-heading"><div><span class="eyebrow">06 · Contact</span><h3>NSTP Office callout</h3></div></div>
        <div class="form-grid">
            <label class="field-group"><span>Section label</span><input name="contact_eyebrow" value="{{ old('contact_eyebrow', $landing['contact_eyebrow']) }}" required></label>
            <label class="field-group"><span>Section heading</span><input name="contact_title" value="{{ old('contact_title', $landing['contact_title']) }}" required></label>
            <label class="field-group full-span"><span>Contact message</span><textarea name="contact_body" rows="3" maxlength="2000" required>{{ old('contact_body', $landing['contact_body']) }}</textarea></label>
        </div>
    </section>

    <section class="card">
        <div class="card-heading"><div><h3>Publish changes</h3><p>Updates become visible on the public landing page immediately after saving.</p></div></div>
        <div class="form-actions"><a class="secondary-outline-button" href="{{ route('landing', ['preview' => 1]) }}" target="_blank" rel="noopener">Preview current page</a><button class="primary-button" type="submit">Publish landing page</button></div>
        <p class="form-help">Last updated by {{ $setting?->updater?->name ?? 'System default' }}{{ $setting?->updated_at ? ' on '.$setting->updated_at->format('M d, Y g:i A') : '' }}.</p>
    </section>
</form>
@endsection
