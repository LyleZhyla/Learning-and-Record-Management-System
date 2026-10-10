@php
    $editorGroups = [
        'hero' => ['Hero section', [
            ['hero_video_url', 'Background video URL', 'url'], ['hero_poster_url', 'Poster image URL', 'url'],
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

<aside class="landing-editor-drawer" aria-label="Landing page editor" data-landing-editor-drawer>
    <header class="landing-editor-heading">
        <div><span>EDITOR MODE</span><strong>Edit the live landing page</strong></div>
        <a href="{{ route('landing', ['preview' => 1]) }}" aria-label="Close editor mode">×</a>
    </header>

    <form id="landing-inline-editor" method="POST" action="{{ route($landingEditorRoutePrefix.'.landing-page.update') }}" data-landing-editor-form>
        @csrf
        @method('PUT')

        <div class="landing-editor-groups">
            @foreach($editorGroups as $sectionId => [$groupLabel, $fields])
                <details class="landing-editor-group" @if($loop->first || collect($fields)->contains(fn ($field) => $errors->has($field[0]))) open @endif>
                    <summary><span>{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>{{ $groupLabel }}<i>⌄</i></summary>
                    <div class="landing-editor-fields">
                        <a class="landing-editor-jump" href="#{{ $sectionId === 'hero' ? 'home' : ($sectionId === 'faqs' ? 'faqs' : $sectionId) }}">Show this section on page ↓</a>
                        @foreach($fields as [$key, $label, $type])
                            <label>
                                <span>{{ $label }}</span>
                                @if($type === 'textarea')
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
