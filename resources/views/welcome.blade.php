<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="The official Smart NSTP portal of Tarlac Agricultural University for student service, community engagement, and program management.">
    <meta name="theme-color" content="#071426">
    <title>Smart NSTP | Tarlac Agricultural University</title>
    <link rel="icon" href="{{ asset('images/branding/tau-logo.png') }}">
    <link rel="preconnect" href="https://www.tau.edu.ph">
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
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
            <a class="brand" href="#home" aria-label="Smart NSTP home"><span class="brand-seal"><img src="{{ asset('images/branding/tau-logo.png') }}" alt="Tarlac Agricultural University seal"></span><span class="brand-copy"><strong>TAU</strong><small>Smart NSTP</small></span><img class="brand-snapie" src="{{ asset('images/characters/snapie-face.webp') }}" alt="" aria-hidden="true"></a>
            <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="site-menu" data-menu-toggle><span></span><span></span><span></span><span class="sr-only">Open menu</span></button>
            <div class="nav-menu" id="site-menu" data-menu>
                <a href="#home">Home</a><a href="#about">About</a><a href="#components">Components</a><a href="#activities">Activities</a><a href="#announcements">Announcements</a><a href="#services">Services</a><a href="#faqs">FAQs</a><a href="#contact">Contact</a>
                <a class="nav-portal" href="{{ route('login') }}">Student Portal <span aria-hidden="true">→</span></a>
            </div>
            <button class="sound-toggle" type="button" aria-label="Toggle background video sound" aria-pressed="false" data-sound-toggle><svg viewBox="0 0 40 40" aria-hidden="true"><path class="speaker-body" d="M8 16h6l8-6v20l-8-6H8z"/><path class="sound-wave" d="M26 15c3 3 3 7 0 10M30 11c5 5 5 13 0 18"/></svg></button>
        </nav>
    </header>

    <main id="main-content">
        <section class="hero" id="home" aria-labelledby="hero-title">
            <video class="hero-video" autoplay muted loop playsinline preload="metadata" poster="{{ $landing['hero_poster_url'] }}" data-hero-video>
                <source src="{{ $landing['hero_video_url'] }}" type="video/mp4">
            </video>
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
                    <article class="component-panel is-active" id="component-rotc" role="tabpanel" aria-labelledby="tab-rotc" data-component-panel="rotc"><div class="component-image"><img src="https://tau.edu.ph/images/Content/2025/613.png" alt="TAU ROTC cadets standing in formation" loading="lazy"><img class="component-mark" src="{{ asset('images/branding/rotc-logo.png') }}" alt=""></div><div class="component-content"><p class="component-code">Reserve Officers' Training Corps</p><h3 data-landing-preview="rotc_title">{{ $landing['rotc_title'] }}</h3><p data-landing-preview="rotc_body">{{ $landing['rotc_body'] }}</p><ul><li>Military discipline and drills</li><li>Leadership and teamwork</li><li>Disaster and emergency readiness</li></ul><a class="button button-outline" href="{{ route('register') }}">Begin registration <span aria-hidden="true">→</span></a></div></article>
                    <article class="component-panel" id="component-cwts" role="tabpanel" aria-labelledby="tab-cwts" data-component-panel="cwts" hidden><div class="component-image"><img src="https://tau.edu.ph/images/Content/2026/64.png" alt="TAU community participating in a university-wide clean-up drive" loading="lazy"><img class="component-mark" src="{{ asset('images/branding/cwts-logo.png') }}" alt=""></div><div class="component-content"><p class="component-code">Civic Welfare Training Service</p><h3 data-landing-preview="cwts_title">{{ $landing['cwts_title'] }}</h3><p data-landing-preview="cwts_body">{{ $landing['cwts_body'] }}</p><ul><li>Community needs assessment</li><li>Environmental and health initiatives</li><li>Project planning and implementation</li></ul><a class="button button-outline" href="{{ route('register') }}">Begin registration <span aria-hidden="true">→</span></a></div></article>
                    <article class="component-panel" id="component-lts" role="tabpanel" aria-labelledby="tab-lts" data-component-panel="lts" hidden><div class="component-image lts-image"><img src="https://www.tau.edu.ph/images/2025/Content/509.png" alt="Official TAU NSTP orientation announcement" loading="lazy"><img class="component-mark" src="{{ asset('images/branding/lts-logo.png') }}" alt=""></div><div class="component-content"><p class="component-code">Literacy Training Service</p><h3 data-landing-preview="lts_title">{{ $landing['lts_title'] }}</h3><p data-landing-preview="lts_body">{{ $landing['lts_body'] }}</p><ul><li>Learning activity design</li><li>Literacy and numeracy facilitation</li><li>Inclusive educational outreach</li></ul><a class="button button-outline" href="{{ route('register') }}">Begin registration <span aria-hidden="true">→</span></a></div></article>
                </div>
            </div>
        </section>

        <section class="section activities-section" id="activities" aria-labelledby="activities-title" data-section-number="03">
            <div class="page-shell">
                <div class="section-intro activities-intro"><div><p class="eyebrow light"><span></span> <span data-landing-preview="activities_eyebrow">{{ $landing['activities_eyebrow'] }}</span></p><h2 id="activities-title" data-landing-preview="activities_title">{{ $landing['activities_title'] }}</h2></div><p data-landing-preview="activities_intro">{{ $landing['activities_intro'] }}</p></div>
                <div class="gallery-filters" role="group" aria-label="Filter activities"><button class="is-active" type="button" data-gallery-filter="all">All</button><button type="button" data-gallery-filter="rotc">ROTC</button><button type="button" data-gallery-filter="cwts">CWTS</button><button type="button" data-gallery-filter="lts">LTS</button><button type="button" data-gallery-filter="photo">Photos</button><button type="button" data-gallery-filter="video">Videos</button></div>
                <div class="activity-grid" data-gallery>
                    <a class="activity-card activity-featured" data-categories="cwts photo" href="https://www.tau.edu.ph/index.php/component/content/article/the-tarlac-agricultural-university-tau-through-the-national-service-training-program-nstp-strengthens-student-emergency-response-skills-during-the-gender-responsive-training?catid=11" target="_blank" rel="noopener"><img src="https://tau.edu.ph/images/Content/2026/70.jpg" alt="NSTP students taking part in first-aid training" loading="lazy"><span class="activity-meta">CWTS · Photo story</span><div><h3>Skills that save lives</h3><p>Gender-responsive first-aid and emergency-response training.</p><span class="view-story">View official story ↗</span></div></a>
                    <a class="activity-card" data-categories="rotc photo" href="https://www.tau.edu.ph/index.php/component/content/article/captured-in-lens-rotc-opener?catid=11" target="_blank" rel="noopener"><img src="https://tau.edu.ph/images/Content/2025/613.png" alt="TAU ROTC cadets in formation" loading="lazy"><span class="activity-meta">ROTC · Photo story</span><div><h3>ROTC opening formation</h3><p>Cadets begin a new cycle of discipline and leadership.</p><span class="view-story">View official story ↗</span></div></a>
                    <a class="activity-card" data-categories="cwts photo" href="https://tau.edu.ph/index.php/component/content/article/spearheaded-by-the-supreme-student-council-ssc-the-university-community-comes-together-today-23-january-for-a-massive-university-wide-clean-up-drive?catid=11" target="_blank" rel="noopener"><img src="https://tau.edu.ph/images/Content/2026/64.png" alt="TAU participants during a campus clean-up drive" loading="lazy"><span class="activity-meta">CWTS · Photo story</span><div><h3>Campus clean-up drive</h3><p>Collective action for a cleaner, safer learning environment.</p><span class="view-story">View official story ↗</span></div></a>
                    <a class="activity-card video-card" data-categories="rotc video" href="https://www.tau.edu.ph/index.php/component/content/article/tarlac-agricultural-university-formally-welcomed-the-regional-annual-administrative-and-tactical-inspection-raati-team-through-arrival-honors-followed-by-a-courtesy-call-to-the-2?catid=11" target="_blank" rel="noopener"><img src="https://tau.edu.ph/images/Content/2025/613.png" alt="ROTC training coverage poster" loading="lazy"><span class="play-mark" aria-hidden="true">▶</span><span class="activity-meta">ROTC · Multimedia</span><div><h3>Readiness under review</h3><p>Follow the official RAATI field coverage from TAU.</p><span class="view-story">Open multimedia story ↗</span></div></a>
                    <a class="activity-card" data-categories="lts photo" href="https://www.tau.edu.ph/index.php/component/content/article/captured-in-lens-the-tarlac-agricultural-university-tau-welcomes-its-new-students-to-the-national-service-training-program-nstp-orientation-at-the-gilberto-o-teodoro-multipurpose-center-on-16-august?catid=11" target="_blank" rel="noopener"><img src="https://www.tau.edu.ph/images/2025/Content/509.png" alt="TAU NSTP orientation announcement" loading="lazy"><span class="activity-meta">LTS · Photo story</span><div><h3>Welcome to NSTP</h3><p>New students discover three distinct paths to nation-building.</p><span class="view-story">View official story ↗</span></div></a>
                    <a class="activity-card video-card" data-categories="cwts lts video" href="https://www.facebook.com/TAUOfficialPage/" target="_blank" rel="noopener"><img src="https://tau.edu.ph/images/Content/2026/70.jpg" alt="NSTP field coverage poster" loading="lazy"><span class="play-mark" aria-hidden="true">▶</span><span class="activity-meta">NSTP · Video updates</span><div><h3>Watch official field updates</h3><p>Visit TAU's official channel for current activity coverage.</p><span class="view-story">Watch on official page ↗</span></div></a>
                </div>
                <p class="gallery-empty" data-gallery-empty hidden>No activities match this filter yet.</p><p class="media-credit">Activity media is linked to official Tarlac Agricultural University news and social channels.</p>
            </div>
        </section>

        <section class="section announcements-section" id="announcements" aria-labelledby="announcements-title" data-section-number="04">
            <div class="page-shell">
                <div class="section-intro compact"><div><p class="eyebrow dark"><span></span> Stay informed</p><h2 id="announcements-title">Latest announcements.</h2></div><a class="text-link dark-link" href="{{ route('login') }}">View all in the portal <span aria-hidden="true">→</span></a></div>
                <div class="announcement-list">
                    @forelse ($announcements as $announcement)
                        <article class="announcement-item"><time datetime="{{ $announcement->published_at?->toDateString() }}"><strong>{{ $announcement->published_at?->format('d') ?? '—' }}</strong><span>{{ $announcement->published_at?->format('M Y') ?? 'Notice' }}</span></time><div><p class="announcement-label">{{ $announcement->component?->code ?? 'NSTP Office' }}</p><h3>{{ $announcement->title }}</h3><p>{{ Str::limit($announcement->body, 180) }}</p></div></article>
                    @empty
                        <article class="announcement-item announcement-placeholder"><time><strong>01</strong><span>Portal</span></time><div><p class="announcement-label">NSTP Office</p><h3>Official announcements are published through Smart NSTP.</h3><p>Sign in to see notices for your component, section, schedules, requirements, and activities.</p></div></article>
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
            <div class="page-shell faq-grid"><div><p class="eyebrow dark"><span></span> <span data-landing-preview="faq_eyebrow">{{ $landing['faq_eyebrow'] }}</span></p><h2 id="faq-title" data-landing-preview="faq_title">{{ $landing['faq_title'] }}</h2><p data-landing-preview="faq_intro">{{ $landing['faq_intro'] }}</p></div><div class="faq-list">@for($faq = 1; $faq <= 5; $faq++)<details><summary><span data-landing-preview="faq_{{ $faq }}_question">{{ $landing['faq_'.$faq.'_question'] }}</span><span aria-hidden="true">+</span></summary><p data-landing-preview="faq_{{ $faq }}_answer">{{ $landing['faq_'.$faq.'_answer'] }}</p></details>@endfor</div></div>
        </section>

        <section class="contact-section" id="contact" aria-labelledby="contact-title"><div class="page-shell contact-grid"><div><p class="eyebrow light"><span></span> <span data-landing-preview="contact_eyebrow">{{ $landing['contact_eyebrow'] }}</span></p><h2 id="contact-title" data-landing-preview="contact_title">{{ $landing['contact_title'] }}</h2><p data-landing-preview="contact_body">{{ $landing['contact_body'] }}</p></div><div class="contact-actions"><a class="button button-gold" href="{{ route('register') }}">Register for NSTP <span aria-hidden="true">→</span></a><a class="button button-ghost" href="https://www.tau.edu.ph" target="_blank" rel="noopener">Visit TAU website ↗</a></div></div></section>
    </main>

    <footer class="site-footer"><div class="page-shell footer-grid"><div class="footer-brand"><img src="{{ asset('images/branding/tau-logo.png') }}" alt=""><div><strong>Tarlac Agricultural University</strong><span>Smart NSTP Management Platform</span></div></div><div><strong>Quick links</strong><a href="#about">About NSTP</a><a href="#components">Components</a><a href="#activities">Activities</a></div><div><strong>Student services</strong><a href="{{ route('register') }}">Register</a><a href="{{ route('service-requests.create') }}">Request a service</a><a href="{{ route('login') }}">Portal login</a><a href="{{ route('serial-numbers.verify') }}">Verify serial number</a></div><div><strong>Campus</strong><span>Malacampa, Camiling</span><span>Tarlac, Philippines</span><a href="https://www.tau.edu.ph" target="_blank" rel="noopener">www.tau.edu.ph ↗</a></div></div><div class="page-shell footer-bottom"><span>© {{ date('Y') }} Tarlac Agricultural University. All rights reserved.</span><a href="#home">Back to top ↑</a></div></footer>
</body>
</html>
