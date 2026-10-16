<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="The official {{ $branding['program_short_name'] }} portal of {{ $branding['organization_name'] }} for student service, community engagement, and program management.">
    <meta name="theme-color" content="#071426">
    <title>{{ $branding['platform_name'] }} | {{ $branding['organization_name'] }}</title>
    <link rel="icon" href="{{ \App\Models\SystemSetting::brandingLogoUrl('organization') }}">
    <link rel="preconnect" href="https://www.tau.edu.ph">
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
    <x-system-colors />
    @if($landingEditorAuthorized)<link rel="stylesheet" href="{{ asset('css/landing-editor.css') }}">@endif
    <script src="{{ asset('js/landing.js') }}" defer></script>
    @if($landingEditorMode)<script src="{{ asset('js/landing-editor.js') }}" defer></script>@endif
</head>
<body @class(['landing-management-preview' => $landingEditorAuthorized, 'landing-editor-mode' => $landingEditorMode])>
    @if($landingEditorAuthorized)
        <nav class="landing-management-bar" aria-label="Landing page management">
            <strong>Landing page</strong>
            <a @class(['is-active' => !$landingEditorMode]) href="{{ route('landing', ['preview' => 1]) }}">Preview</a>
            <a @class(['is-active' => $landingEditorMode]) href="{{ route('landing', ['preview' => 1, 'editor' => 1]) }}">Editor mode</a>
            <a href="{{ route(auth()->user()->dashboardRouteName()) }}">Back to dashboard</a>
        </nav>
        @if(session('status'))<div class="landing-management-notice" role="status">{{ session('status') }}</div>@endif
        @if($landingEditorMode)@include('landing-page._inline-editor')@endif
    @endif
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <header class="site-header" data-header>
        <nav class="main-nav page-shell" aria-label="Main navigation">
            <a class="brand" href="#home" aria-label="{{ $branding['platform_name'] }} home"><span class="brand-seal"><img src="{{ \App\Models\SystemSetting::brandingLogoUrl('organization') }}" alt="{{ $branding['organization_name'] }} logo"></span><span class="brand-copy"><strong>{{ $branding['organization_short_name'] }}</strong><small>{{ $branding['program_short_name'] }}</small></span><img class="brand-snapie" src="{{ asset('images/characters/snapie-face.webp') }}" alt="" aria-hidden="true"></a>
            <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="site-menu" data-menu-toggle><span></span><span></span><span></span><span class="sr-only">Open menu</span></button>
            <div class="nav-menu" id="site-menu" data-menu>
                <a href="#home">Home</a><a href="#about">About</a><a href="#components">Components</a><a href="#activities">Activities</a><a href="#announcements">Announcements</a><a href="#services">Services</a><a href="#faqs">FAQs</a><a href="#contact">Contact</a>
                <a class="nav-portal" href="{{ route('login') }}">Student Portal <span aria-hidden="true">→</span></a>
            </div>
        </nav>
    </header>

    <main id="main-content">
        <section class="hero" id="home" aria-labelledby="hero-title">
            <div class="hero-slideshow" data-hero-slideshow aria-hidden="true">
                @foreach([
                    [$landing['hero_slide_1_url'], 'center 42%'],
                    [$landing['hero_slide_2_url'], 'center 38%'],
                    [$landing['hero_slide_3_url'], 'center 44%'],
                    [$landing['hero_slide_4_url'], 'center 36%'],
                ] as [$image, $position])
                    <figure class="hero-slide {{ $loop->first ? 'is-active' : '' }}" data-hero-slide style="--slide-position: {{ $position }}"><img src="{{ $image }}" alt="" @if($loop->first) fetchpriority="high" @else loading="lazy" @endif></figure>
                @endforeach
            </div>
            <div class="hero-shade hero-shade-horizontal" aria-hidden="true"></div>
            <div class="hero-shade hero-shade-vertical" aria-hidden="true"></div>
            <div class="hero-shade hero-shade-corner" aria-hidden="true"></div>
            <div class="page-shell hero-grid">
                <div class="hero-copy">
                    <p class="hero-brand" data-landing-preview="hero_brand">{{ $landing['hero_brand'] }}</p>
                    <h1 id="hero-title"><span data-landing-preview="hero_line_1">{{ $landing['hero_line_1'] }}</span><span data-landing-preview="hero_line_2">{{ $landing['hero_line_2'] }}</span><span data-landing-preview="hero_line_3">{{ $landing['hero_line_3'] }}</span></h1>
                    <p class="hero-lead"><span data-landing-preview="hero_lead_1">{{ $landing['hero_lead_1'] }}</span><span data-landing-preview="hero_lead_2">{{ $landing['hero_lead_2'] }}</span></p>
                    <div class="hero-actions"><a class="button hero-cta" href="{{ route('register') }}">Register for NSTP <span class="action-disc" aria-hidden="true">→</span></a><a class="text-link" href="#components">Explore the program <span aria-hidden="true">↗</span></a></div>
                </div>
                <div class="hero-slide-controls" data-hero-controls aria-label="Hero slideshow controls">
                    <button type="button" data-hero-previous aria-label="Show previous image">←</button>
                    <div role="group" aria-label="Choose a hero image">
                        @for($slide = 0; $slide < 4; $slide++)<button type="button" data-hero-dot="{{ $slide }}" aria-label="Show image {{ $slide + 1 }}" aria-current="{{ $slide === 0 ? 'true' : 'false' }}"></button>@endfor
                    </div>
                    <span data-hero-count>01 / 04</span>
                    <button type="button" data-hero-next aria-label="Show next image">→</button>
                </div>
                <a class="hero-scroll" href="#about">Scroll for program details <span aria-hidden="true">⌄</span></a>
            </div>
        </section>

        <section class="section about-section" id="about" aria-labelledby="about-title" data-section-number="01">
            <div class="page-shell about-grid">
                <div class="section-heading"><p class="eyebrow dark"><span></span> <span data-landing-preview="about_eyebrow">{{ $landing['about_eyebrow'] }}</span></p><h2 id="about-title" data-landing-preview="about_title">{{ $landing['about_title'] }}</h2></div>
                <div class="about-copy"><p class="lead-paragraph" data-landing-preview="about_lead">{{ $landing['about_lead'] }}</p><p data-landing-preview="about_body">{{ $landing['about_body'] }}</p><div class="law-note"><span data-landing-preview="about_law_title">{{ $landing['about_law_title'] }}</span><p data-landing-preview="about_law_body">{{ $landing['about_law_body'] }}</p></div></div>
            </div>
        </section>

        <section class="section components-section" id="components" aria-labelledby="components-title" data-section-number="02">
            <div class="page-shell">
                <div class="section-intro"><div><p class="eyebrow dark"><span></span> <span data-landing-preview="components_eyebrow">{{ $landing['components_eyebrow'] }}</span></p><h2 id="components-title" data-landing-preview="components_title">{{ $landing['components_title'] }}</h2></div><p data-landing-preview="components_intro">{{ $landing['components_intro'] }}</p></div>
                <div class="component-tabs" role="tablist" aria-label="NSTP components"><button type="button" role="tab" aria-selected="true" aria-controls="component-rotc" id="tab-rotc" data-component-tab="rotc"><span>01</span><strong>ROTC</strong><small>Defense preparedness</small></button><button type="button" role="tab" aria-selected="false" aria-controls="component-cwts" id="tab-cwts" data-component-tab="cwts"><span>02</span><strong>CWTS</strong><small>Community welfare</small></button><button type="button" role="tab" aria-selected="false" aria-controls="component-lts" id="tab-lts" data-component-tab="lts"><span>03</span><strong>LTS</strong><small>Literacy service</small></button></div>
                <div class="component-panels">
                    <article class="component-panel is-active" id="component-rotc" role="tabpanel" aria-labelledby="tab-rotc" data-component-panel="rotc"><div class="component-image"><img src="{{ $landing['rotc_image_url'] }}" alt="ROTC cadets standing in formation" loading="lazy" data-landing-preview-image="rotc_image_url"><img class="component-mark" src="{{ $components->get('ROTC')?->logoUrl() ?: asset('images/branding/rotc-logo.png') }}" alt=""></div><div class="component-content"><p class="component-code">{{ $components->get('ROTC')?->name ?? "Reserve Officers' Training Corps" }}</p><h3 data-landing-preview="rotc_title">{{ $landing['rotc_title'] }}</h3><p data-landing-preview="rotc_body">{{ $landing['rotc_body'] }}</p><ul><li>Military discipline and drills</li><li>Leadership and teamwork</li><li>Disaster and emergency readiness</li></ul><a class="button button-outline" href="{{ route('register') }}">Begin registration <span aria-hidden="true">→</span></a></div></article>
                    <article class="component-panel" id="component-cwts" role="tabpanel" aria-labelledby="tab-cwts" data-component-panel="cwts" hidden><div class="component-image"><img src="{{ $landing['cwts_image_url'] }}" alt="Community participating in a clean-up drive" loading="lazy" data-landing-preview-image="cwts_image_url"><img class="component-mark" src="{{ $components->get('CWTS')?->logoUrl() ?: asset('images/branding/cwts-logo.png') }}" alt=""></div><div class="component-content"><p class="component-code">{{ $components->get('CWTS')?->name ?? 'Civic Welfare Training Service' }}</p><h3 data-landing-preview="cwts_title">{{ $landing['cwts_title'] }}</h3><p data-landing-preview="cwts_body">{{ $landing['cwts_body'] }}</p><ul><li>Community needs assessment</li><li>Environmental and health initiatives</li><li>Project planning and implementation</li></ul><a class="button button-outline" href="{{ route('register') }}">Begin registration <span aria-hidden="true">→</span></a></div></article>
                    <article class="component-panel" id="component-lts" role="tabpanel" aria-labelledby="tab-lts" data-component-panel="lts" hidden><div class="component-image lts-image"><img src="{{ $landing['lts_image_url'] }}" alt="NSTP orientation announcement" loading="lazy" data-landing-preview-image="lts_image_url"><img class="component-mark" src="{{ $components->get('LTS')?->logoUrl() ?: asset('images/branding/lts-logo.png') }}" alt=""></div><div class="component-content"><p class="component-code">{{ $components->get('LTS')?->name ?? 'Literacy Training Service' }}</p><h3 data-landing-preview="lts_title">{{ $landing['lts_title'] }}</h3><p data-landing-preview="lts_body">{{ $landing['lts_body'] }}</p><ul><li>Learning activity design</li><li>Literacy and numeracy facilitation</li><li>Inclusive educational outreach</li></ul><a class="button button-outline" href="{{ route('register') }}">Begin registration <span aria-hidden="true">→</span></a></div></article>
                </div>
            </div>
        </section>

        <section class="section activities-section" id="activities" aria-labelledby="activities-title" data-section-number="03">
            <div class="page-shell">
                <div class="section-intro activities-intro"><div><p class="eyebrow light"><span></span> <span data-landing-preview="activities_eyebrow">{{ $landing['activities_eyebrow'] }}</span></p><h2 id="activities-title" data-landing-preview="activities_title">{{ $landing['activities_title'] }}</h2></div><p data-landing-preview="activities_intro">{{ $landing['activities_intro'] }}</p></div>
                <div class="gallery-filters" role="group" aria-label="Filter activities by NSTP component"><button class="is-active" type="button" data-gallery-filter="all">All components</button><button type="button" data-gallery-filter="rotc">ROTC</button><button type="button" data-gallery-filter="cwts">CWTS</button><button type="button" data-gallery-filter="lts">LTS</button></div>
                <div class="activity-grid" data-gallery>
                    @foreach($landingMedia as $media)
                        <a class="activity-card {{ $media->type === 'video' ? 'video-card' : '' }}" data-categories="{{ $media->component }}" href="{{ $media->fileUrl() }}" target="_blank" rel="noopener">
                            @if($media->type === 'video')
                                <video muted playsinline preload="metadata"><source src="{{ $media->fileUrl() }}"></video><span class="play-mark" aria-hidden="true">▶</span>
                            @else
                                <img src="{{ $media->fileUrl() }}" alt="{{ $media->title }}" loading="lazy">
                            @endif
                            <span class="activity-meta">{{ strtoupper($media->component) }}@if($media->event_date) · {{ $media->event_date->format('M j, Y') }}@endif</span>
                            <div><h3>{{ $media->title }}</h3>@if($media->description)<p>{{ $media->description }}</p>@endif</div>
                            @if($media->location)<small>{{ $media->location }}</small>@endif
                        </a>
                    @endforeach
                </div>
                <p class="gallery-empty" data-gallery-empty @if($landingMedia->isNotEmpty()) hidden @endif>No media has been published for this component yet.</p><p class="media-credit" data-landing-preview="activities_credit">{{ $landing['activities_credit'] }}</p>
            </div>
        </section>

        <section class="section announcements-section" id="announcements" aria-labelledby="announcements-title" data-section-number="04">
            <div class="page-shell">
                <div class="section-intro compact"><div><p class="eyebrow dark"><span></span> <span data-landing-preview="announcements_eyebrow">{{ $landing['announcements_eyebrow'] }}</span></p><h2 id="announcements-title" data-landing-preview="announcements_title">{{ $landing['announcements_title'] }}</h2></div><a class="text-link dark-link" href="{{ route('login') }}"><span data-landing-preview="announcements_link_label">{{ $landing['announcements_link_label'] }}</span> <span aria-hidden="true">→</span></a></div>
                <div class="announcement-list">
                    @forelse ($announcements as $announcement)
                        <article class="announcement-item"><time datetime="{{ $announcement->published_at?->toDateString() }}"><strong>{{ $announcement->published_at?->format('d') ?? '—' }}</strong><span>{{ $announcement->published_at?->format('M Y') ?? 'Notice' }}</span></time><div><p class="announcement-label">{{ $announcement->component?->code ?? 'NSTP Office' }}</p><h3>{{ $announcement->title }}</h3><p>{{ Str::limit($announcement->body, 180) }}</p></div></article>
                    @empty
                        <article class="announcement-item announcement-placeholder"><time><strong>01</strong><span>Portal</span></time><div><p class="announcement-label">NSTP Office</p><h3 data-landing-preview="announcements_empty_title">{{ $landing['announcements_empty_title'] }}</h3><p data-landing-preview="announcements_empty_body">{{ $landing['announcements_empty_body'] }}</p></div></article>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="section services-section" id="services" aria-labelledby="services-title">
            <div class="page-shell services-grid">
                <div class="services-heading"><p class="eyebrow light"><span></span> <span data-landing-preview="services_eyebrow">{{ $landing['services_eyebrow'] }}</span></p><h2 id="services-title" data-landing-preview="services_title">{{ $landing['services_title'] }}</h2><p data-landing-preview="services_intro">{{ $landing['services_intro'] }}</p><a class="button button-primary service-request-button" href="{{ route('service-requests.create') }}">Request a service <span aria-hidden="true">→</span></a><div class="snapie-service-note"><img class="landing-snapie services-snapie" src="{{ asset('images/characters/snapie-qr.webp') }}" alt="SNAPIE, the Smart NSTP digital assistant"><p><strong data-landing-preview="snapie_title">{{ $landing['snapie_title'] }}</strong><span data-landing-preview="snapie_body">{{ $landing['snapie_body'] }}</span></p></div></div>
                <div class="service-links"><a href="{{ route('register') }}"><span class="service-number">01</span><span><strong>Student registration</strong><small>Submit your NSTP application and requirements.</small></span><span aria-hidden="true">↗</span></a><a href="{{ route('login') }}"><span class="service-number">02</span><span><strong>Student portal</strong><small>View attendance, assessments, documents, and updates.</small></span><span aria-hidden="true">↗</span></a><a href="{{ route('serial-numbers.verify') }}"><span class="service-number">03</span><span><strong>Serial number verifier</strong><small>Verify an issued NSTP completion serial number.</small></span><span aria-hidden="true">↗</span></a><a href="{{ route('service-requests.create') }}"><span class="service-number">04</span><span><strong>Records &amp; assistance requests</strong><small>Request a serial number, certificate, certification, Honor Guard, Colors, Marshal, or collaboration.</small></span><span aria-hidden="true">↗</span></a><a href="{{ route('password.request') }}"><span class="service-number">05</span><span><strong>Account recovery</strong><small>Request help accessing an existing account.</small></span><span aria-hidden="true">↗</span></a></div>
            </div>
        </section>

        <section class="section faq-section" id="faqs" aria-labelledby="faq-title">
            <div class="page-shell faq-grid"><div><p class="eyebrow dark"><span></span> <span data-landing-preview="faq_eyebrow">{{ $landing['faq_eyebrow'] }}</span></p><h2 id="faq-title" data-landing-preview="faq_title">{{ $landing['faq_title'] }}</h2><p data-landing-preview="faq_intro">{{ $landing['faq_intro'] }}</p></div><div class="faq-list">@for($faq = 1; $faq <= 10; $faq++)<details><summary><span data-landing-preview="faq_{{ $faq }}_question">{{ $landing['faq_'.$faq.'_question'] }}</span><span aria-hidden="true">+</span></summary><p data-landing-preview="faq_{{ $faq }}_answer">{{ $landing['faq_'.$faq.'_answer'] }}</p></details>@endfor</div></div>
        </section>

        <section class="contact-section" id="contact" aria-labelledby="contact-title"><div class="page-shell contact-grid"><div><p class="eyebrow light"><span></span> <span data-landing-preview="contact_eyebrow">{{ $landing['contact_eyebrow'] }}</span></p><h2 id="contact-title" data-landing-preview="contact_title">{{ $landing['contact_title'] }}</h2><p data-landing-preview="contact_body">{{ $landing['contact_body'] }}</p></div><div class="contact-actions"><a class="button button-gold" href="{{ route('register') }}"><span data-landing-preview="contact_primary_label">{{ $landing['contact_primary_label'] }}</span> <span aria-hidden="true">→</span></a>@if($branding['organization_website'])<a class="button button-ghost" href="{{ $branding['organization_website'] }}" target="_blank" rel="noopener"><span data-landing-preview="contact_secondary_label">{{ $landing['contact_secondary_label'] }}</span> ↗</a>@endif</div></div></section>
    </main>

    <footer class="site-footer"><div class="page-shell footer-grid"><div class="footer-brand"><img src="{{ \App\Models\SystemSetting::brandingLogoUrl('organization') }}" alt=""><div><strong>{{ $branding['organization_name'] }}</strong><span data-landing-preview="footer_description">{{ $landing['footer_description'] }}</span></div></div><div><strong data-landing-preview="footer_quick_links_title">{{ $landing['footer_quick_links_title'] }}</strong><a href="#about">About {{ $branding['program_short_name'] }}</a><a href="#components">Components</a><a href="#activities">Activities</a></div><div><strong data-landing-preview="footer_services_title">{{ $landing['footer_services_title'] }}</strong><a href="{{ route('register') }}">Register</a><a href="{{ route('service-requests.create') }}">Request a service</a><a href="{{ route('login') }}">Portal login</a><a href="{{ route('serial-numbers.verify') }}">Verify serial number</a></div><div><strong data-landing-preview="footer_institution_title">{{ $landing['footer_institution_title'] }}</strong>@if($branding['organization_website'])<a href="{{ $branding['organization_website'] }}" target="_blank" rel="noopener">Official website ↗</a>@endif</div></div><div class="page-shell footer-bottom"><span data-landing-preview="footer_copyright">{{ $landing['footer_copyright'] }}</span><a href="#home">Back to top ↑</a></div></footer>
    <button class="back-to-top-button" type="button" data-back-to-top aria-label="Back to top" title="Back to top" hidden><span aria-hidden="true">↑</span></button>
</body>
</html>
