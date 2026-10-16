@extends($layout)
@section('title', $mediaOnly ? 'Landing News & Media' : 'Landing Page Editor')
@section('page-title', $mediaOnly ? 'Landing News & Media' : 'Landing Page Editor')

@section('content')
<style>
    .landing-editor-form{display:grid;gap:20px}.landing-editor-form .full-span{grid-column:1/-1}.landing-editor-form textarea{width:100%;resize:vertical;border:1px solid #dbe2ec;border-radius:10px;background:#fbfcfe;padding:12px 13px;color:var(--ink);font:inherit;line-height:1.55;outline:none}.landing-editor-form textarea:focus{border-color:#4f83d5;box-shadow:0 0 0 3px rgba(79,131,213,.1);background:white}.landing-image-upload{padding:7px!important;border:1px dashed #9fb7d5!important;background:#f4f8fd!important;color:#5e6d82!important;font-size:12px;cursor:pointer}.landing-image-upload::file-selector-button{min-height:38px;margin-right:11px;border:0;border-radius:8px;background:#2468ca;padding:0 15px;color:#fff;font:800 12px/1 Arial,sans-serif;cursor:pointer;transition:background .2s,transform .2s}.landing-image-upload:hover::file-selector-button{background:#174f9d}.landing-image-upload:active::file-selector-button{transform:translateY(1px)}.landing-image-upload:focus-visible{border-color:#2468ca!important;outline:3px solid rgba(36,104,202,.14)}.landing-media-workspace{display:grid;grid-template-columns:minmax(300px,.8fr) minmax(0,1.2fr);align-items:start;gap:18px;margin-bottom:20px}.landing-media-upload-card,.landing-media-library{padding:24px}.landing-media-upload-form{display:grid;gap:13px}.landing-media-upload-form .form-grid{gap:12px}.landing-media-library-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.landing-media-item{overflow:hidden;border:1px solid var(--line);border-radius:14px;background:var(--surface)}.landing-media-item img,.landing-media-item video{display:block;width:100%;height:170px;background:#071426;object-fit:cover}.landing-media-item-copy{display:grid;gap:5px;padding:13px}.landing-media-item-copy>span{color:var(--blue);font-size:9px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.landing-media-item-copy strong{color:var(--navy);font-size:12px}.landing-media-item-copy p{margin:0;color:var(--muted);font-size:9px;line-height:1.45}.landing-media-item footer{display:flex;align-items:center;justify-content:space-between;gap:12px;border-top:1px solid var(--line);padding:10px 13px}.landing-media-item footer small{color:var(--muted);font-size:8px}.landing-media-delete{border:0;background:transparent;color:#b43248;font:inherit;font-size:9px;font-weight:800;cursor:pointer}.landing-content-heading{display:flex;align-items:end;justify-content:space-between;gap:20px;margin:30px 0 14px}.landing-content-heading h3{margin:4px 0 0;color:var(--navy);font-size:22px}.landing-content-heading p{margin:0;color:var(--muted);font-size:10px}html[data-theme=dark] .landing-editor-form textarea{border-color:#30405a;background:#111d2d;color:#edf4ff}html[data-theme=dark] .landing-image-upload{border-color:#44658c!important;background:#111d2d!important;color:#c5d3e5!important}html[data-theme=dark] .landing-media-item-copy strong,html[data-theme=dark] .landing-content-heading h3{color:var(--ink)}@media(max-width:980px){.landing-media-workspace{grid-template-columns:1fr}}@media(max-width:760px){.landing-editor-form .full-span{grid-column:auto}.landing-media-library-grid{grid-template-columns:1fr}.landing-content-heading{align-items:flex-start;flex-direction:column}}
</style>
<div class="page-actions">
    <div><span class="eyebrow">Public website</span><h2>{{ $mediaOnly ? 'Component news & media' : 'Landing page content & media' }}</h2><p>{{ $mediaOnly ? 'Publish photos or videos under the correct NSTP component.' : 'Manage text, slideshow photos, and component-based gallery media from one workspace.' }}</p></div>
    <a class="secondary-outline-button" href="{{ route('landing', ['preview' => 1]) }}" target="_blank" rel="noopener">Preview landing page ↗</a>
</div>

<div class="landing-media-workspace">
    <section class="card landing-media-upload-card">
        <div class="card-heading"><div><span class="eyebrow">Media upload</span><h3>Add a photo or video</h3><p>Uploaded media is saved to the database-backed landing-page library and displayed in Activities.</p></div></div>
        <form class="landing-media-upload-form" method="POST" action="{{ route($mediaStoreRoute) }}" enctype="multipart/form-data">
            @csrf
            <div class="form-grid">
                <label class="field-group"><span>Media type</span><select name="media_type" required><option value="photo" @selected(old('media_type') === 'photo')>Photo</option><option value="video" @selected(old('media_type') === 'video')>Video</option></select></label>
                @if($allowedComponent)
                    <label class="field-group"><span>NSTP component</span><input value="{{ strtoupper($allowedComponent) }}" readonly><input type="hidden" name="media_component" value="{{ $allowedComponent }}"></label>
                @else
                    <label class="field-group"><span>NSTP component</span><select name="media_component" required><option value="">Select component</option><option value="rotc" @selected(old('media_component') === 'rotc')>ROTC</option><option value="cwts" @selected(old('media_component') === 'cwts')>CWTS</option><option value="lts" @selected(old('media_component') === 'lts')>LTS</option></select></label>
                @endif
                <label class="field-group full"><span>Title</span><input name="media_title" value="{{ old('media_title') }}" maxlength="180" required></label>
                <label class="field-group full"><span>Description</span><textarea name="media_description" rows="3" maxlength="1000">{{ old('media_description') }}</textarea></label>
                <label class="field-group full"><span>Photo or video file</span><input class="landing-image-upload" type="file" name="media_file" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime" required><small class="form-help">Photos: JPG, PNG, WebP up to 10 MB. Videos: MP4, WebM, MOV up to 100 MB.</small></label>
            </div>
            <div class="form-actions"><button class="primary-button" type="submit">Upload to media library</button></div>
        </form>
    </section>

    <section class="card landing-media-library">
        <div class="card-heading"><div><span class="eyebrow">Media library</span><h3>{{ $landingMedia->count() }} uploaded {{ Str::plural('item', $landingMedia->count()) }}</h3><p>These photos and videos appear in the public Activities gallery.</p></div></div>
        <div class="landing-media-library-grid">
            @forelse($landingMedia as $media)
                <article class="landing-media-item">
                    @if($media->type === 'video')
                        <video controls preload="metadata"><source src="{{ $media->fileUrl() }}"></video>
                    @else
                        <img src="{{ $media->fileUrl() }}" alt="{{ $media->title }}">
                    @endif
                    <div class="landing-media-item-copy"><span>{{ strtoupper($media->component) }} · {{ ucfirst($media->type) }}</span><strong>{{ $media->title }}</strong>@if($media->description)<p>{{ $media->description }}</p>@endif</div>
                    <footer><small>Uploaded {{ $media->created_at->format('M j, Y') }}</small><form method="POST" action="{{ route($mediaDestroyRoute, $media) }}" onsubmit="return confirm('Remove this media item from the landing page?');">@csrf @method('DELETE')<button class="landing-media-delete" type="submit">Remove</button></form></footer>
                </article>
            @empty
                <div class="empty-state"><strong>No uploaded media yet</strong><span>Add a photo or video using the form.</span></div>
            @endforelse
        </div>
    </section>
</div>

@unless($mediaOnly)
<div class="landing-content-heading"><div><span class="eyebrow">Page content</span><h3>Edit sections and slideshow</h3></div><p>Save all text and hero slideshow changes together.</p></div>

<form class="landing-editor-form" method="POST" action="{{ route($routePrefix.'.landing-page.update') }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    <input type="hidden" name="editor_context" value="full">

    <section class="card form-card">
        <div class="card-heading"><div><span class="eyebrow">01 · Hero</span><h3>Opening message and slideshow</h3><p>Add four landscape images. The slideshow automatically pauses when a visitor interacts with its controls.</p></div></div>
        <div class="form-grid">
            @for($slide = 1; $slide <= 4; $slide++)
                <div class="field-group"><span>Slideshow image {{ $slide }}</span><img src="{{ old('hero_slide_'.$slide.'_url', $landing['hero_slide_'.$slide.'_url']) }}" alt="Current slideshow image {{ $slide }}" style="width:100%;max-height:180px;object-fit:cover;border-radius:10px"><input name="hero_slide_{{ $slide }}_url" value="{{ old('hero_slide_'.$slide.'_url', $landing['hero_slide_'.$slide.'_url']) }}" required><input class="landing-image-upload" type="file" name="hero_slide_{{ $slide }}_image" accept="image/jpeg,image/png,image/webp"><small class="form-help">Upload JPG, PNG, or WebP up to 5 MB, or retain the existing URL/path.</small>@error('hero_slide_'.$slide.'_url')<small class="field-error">{{ $message }}</small>@enderror @error('hero_slide_'.$slide.'_image')<small class="field-error">{{ $message }}</small>@enderror</div>
            @endfor
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
@endunless
@endsection
