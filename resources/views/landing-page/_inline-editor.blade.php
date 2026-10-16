@php
    $editorGroups = [
        'hero' => ['Hero section', [
            ['hero_slide_1_url', 'Slideshow image 1', 'image'], ['hero_slide_2_url', 'Slideshow image 2', 'image'],
            ['hero_slide_3_url', 'Slideshow image 3', 'image'], ['hero_slide_4_url', 'Slideshow image 4', 'image'],
            ['hero_brand', 'Brand label', 'text'], ['hero_line_1', 'Headline line 1', 'text'],
            ['hero_line_2', 'Headline line 2', 'text'], ['hero_line_3', 'Headline line 3', 'text'],
            ['hero_lead_1', 'Supporting line 1', 'textarea'], ['hero_lead_2', 'Supporting line 2', 'textarea'],
        ]],
        'about' => ['About NSTP', [
            ['about_eyebrow', 'Section label', 'text'], ['about_title', 'Section heading', 'text'],
            ['about_lead', 'Lead statement', 'textarea'], ['about_body', 'Program description', 'textarea'],
            ['about_law_title', 'Legal reference', 'text'], ['about_law_body', 'Legal description', 'textarea'],
        ]],
        'components' => ['Program components', [
            ['components_eyebrow', 'Section label', 'text'], ['components_title', 'Section heading', 'text'],
            ['components_intro', 'Section introduction', 'textarea'], ['rotc_title', 'ROTC heading', 'text'],
            ['rotc_body', 'ROTC description', 'textarea'], ['cwts_title', 'CWTS heading', 'text'],
            ['cwts_body', 'CWTS description', 'textarea'], ['lts_title', 'LTS heading', 'text'],
            ['lts_body', 'LTS description', 'textarea'],
        ]],
        'activities' => ['Activities', [
            ['activities_eyebrow', 'Section label', 'text'], ['activities_title', 'Section heading', 'text'],
            ['activities_intro', 'Section introduction', 'textarea'],
        ]],
        'services' => ['Services and SNAPIE', [
            ['services_eyebrow', 'Section label', 'text'], ['services_title', 'Section heading', 'text'],
            ['services_intro', 'Section introduction', 'textarea'], ['snapie_title', 'SNAPIE heading', 'text'],
            ['snapie_body', 'SNAPIE message', 'textarea'],
        ]],
        'faqs' => ['Frequently asked questions', array_merge([
            ['faq_eyebrow', 'Section label', 'text'], ['faq_title', 'Section heading', 'text'],
            ['faq_intro', 'Section introduction', 'textarea'],
        ], collect(range(1, 5))->flatMap(fn ($number) => [
            ["faq_{$number}_question", "Question {$number}", 'text'],
            ["faq_{$number}_answer", "Answer {$number}", 'textarea'],
        ])->all())],
        'contact' => ['Contact section', [
            ['contact_eyebrow', 'Section label', 'text'], ['contact_title', 'Section heading', 'text'],
            ['contact_body', 'Contact message', 'textarea'],
        ]],
    ];
@endphp

<style>
    .landing-editor-image-preview { width: 100%; height: 118px; border: 1px solid #dce4ef; border-radius: 10px; object-fit: cover; }
    .landing-editor-fields .landing-editor-upload-help { color: #6e7c90; line-height: 1.45; }
    .landing-editor-fields .landing-image-upload { padding: 6px; color: #5e6d82; border: 1px dashed #9fb7d5; background: #f4f8fd; font-size: 11px; cursor: pointer; }
    .landing-image-upload::file-selector-button { min-height: 36px; margin-right: 10px; border: 0; border-radius: 8px; background: #2468ca; padding: 0 13px; color: #fff; font: 800 11px/1 Arial, sans-serif; cursor: pointer; transition: background .2s, transform .2s; }
    .landing-image-upload:hover::file-selector-button { background: #174f9d; }
    .landing-image-upload:active::file-selector-button { transform: translateY(1px); }
    .landing-image-upload:focus-visible { border-color: #2468ca; outline: 3px solid rgba(36, 104, 202, .14); }
    .landing-editor-heading-actions { display: flex; align-items: center; gap: 7px; }
    .landing-editor-heading-actions a { min-width: 38px; height: 38px; display: grid; place-items: center; padding: 0 10px; color: #fff; border: 1px solid rgba(255,255,255,.18); border-radius: 999px; font: 800 11px Arial, sans-serif; text-decoration: none; }
    .landing-editor-heading-actions a:last-child { padding: 0; font-size: 24px; }
</style>

<aside class="landing-editor-drawer" aria-label="Landing page editor" data-landing-editor-drawer>
    <header class="landing-editor-heading">
        <div><span>EDITOR MODE</span><strong>Edit the live landing page</strong></div>
        <div class="landing-editor-heading-actions"><a href="{{ route('admin.landing-page.edit') }}">Media</a><a href="{{ route('landing', ['preview' => 1]) }}" aria-label="Close editor mode">×</a></div>
    </header>

    <form id="landing-inline-editor" method="POST" action="{{ route($landingEditorRoutePrefix.'.landing-page.update') }}" enctype="multipart/form-data" data-landing-editor-form>
        @csrf
        @method('PUT')
        <input type="hidden" name="editor_context" value="inline">

        <div class="landing-editor-groups">
            @foreach($editorGroups as $sectionId => [$groupLabel, $fields])
                <details class="landing-editor-group" @if($loop->first || collect($fields)->contains(fn ($field) => $errors->has($field[0]))) open @endif>
                    <summary><span>{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>{{ $groupLabel }}<i>⌄</i></summary>
                    <div class="landing-editor-fields">
                        <a class="landing-editor-jump" href="#{{ $sectionId === 'hero' ? 'home' : ($sectionId === 'faqs' ? 'faqs' : $sectionId) }}">Show this section on page ↓</a>
                        @foreach($fields as [$key, $label, $type])
                            <label>
                                <span>{{ $label }}</span>
                                @if($type === 'image')
                                    <img class="landing-editor-image-preview" src="{{ old($key, $landing[$key]) }}" alt="Current {{ strtolower($label) }}" data-landing-upload-preview="{{ $key }}">
                                    <input type="text" name="{{ $key }}" value="{{ old($key, $landing[$key]) }}" maxlength="2048" required data-landing-editor-field="{{ $key }}">
                                    <input class="landing-image-upload" type="file" name="{{ str_replace('_url', '_image', $key) }}" accept="image/jpeg,image/png,image/webp" data-landing-image-upload="{{ $key }}">
                                    <small class="landing-editor-upload-help">Upload JPG, PNG, or WebP up to 5 MB. A selected file replaces the URL above when published.</small>
                                    @error(str_replace('_url', '_image', $key))<small>{{ $message }}</small>@enderror
                                @elseif($type === 'textarea')
                                    <textarea name="{{ $key }}" rows="3" maxlength="2000" required data-landing-editor-field="{{ $key }}">{{ old($key, $landing[$key]) }}</textarea>
                                @else
                                    <input type="{{ $type }}" name="{{ $key }}" value="{{ old($key, $landing[$key]) }}" maxlength="{{ $type === 'url' ? 2048 : 180 }}" required data-landing-editor-field="{{ $key }}">
                                @endif
                                @error($key)<small>{{ $message }}</small>@enderror
                            </label>
                        @endforeach
                    </div>
                </details>
            @endforeach
        </div>

        <footer class="landing-editor-actions">
            <p><span data-editor-save-state>Live preview</span><small>Changes are not public until published.</small></p>
            <button type="submit">Publish changes</button>
        </footer>
    </form>
</aside>
