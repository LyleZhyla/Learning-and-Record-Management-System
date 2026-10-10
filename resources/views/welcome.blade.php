<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="The official Smart NSTP portal of Tarlac Agricultural University for student service, community engagement, and program management.">
    <meta name="theme-color" content="#123f2e">
    <title>Smart NSTP | Tarlac Agricultural University</title>
    <link rel="icon" href="{{ asset('images/branding/tau-logo.png') }}">
    <link rel="preconnect" href="https://www.tau.edu.ph">
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
    <script src="{{ asset('js/landing.js') }}" defer></script>
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <header class="site-header" data-header>
        <div class="utility-bar"><div class="page-shell utility-inner"><span>Republic of the Philippines</span><a href="https://www.tau.edu.ph" target="_blank" rel="noopener">Tarlac Agricultural University website ↗</a></div></div>
        <nav class="main-nav page-shell" aria-label="Main navigation">
            <a class="brand" href="#home" aria-label="Smart NSTP home"><img src="{{ asset('images/branding/tau-logo.png') }}" alt="Tarlac Agricultural University seal"><span class="brand-copy"><strong>Tarlac Agricultural University</strong><small>National Service Training Program</small></span></a>
            <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="site-menu" data-menu-toggle><span></span><span></span><span></span><span class="sr-only">Open menu</span></button>
            <div class="nav-menu" id="site-menu" data-menu>
                <a href="#home">Home</a><a href="#about">About</a><a href="#components">Components</a><a href="#activities">Activities</a><a href="#announcements">Announcements</a><a href="#services">Services</a><a href="#faqs">FAQs</a><a href="#contact">Contact</a>
                <a class="nav-portal" href="{{ route('login') }}">Student Portal <span aria-hidden="true">→</span></a>
            </div>
        </nav>
    </header>

    <main id="main-content">
        <section class="hero" id="home" aria-labelledby="hero-title">
            <div class="page-shell hero-grid">
                <div class="hero-copy">
                    <p class="eyebrow"><span></span> Service. Citizenship. Leadership.</p>
                    <h1 id="hero-title">Empowering Students.<br><em>Serving Communities.</em><br>Building Future Leaders.</h1>
                    <p class="hero-lead">Smart NSTP connects TAU students with purposeful training, community service, and the tools they need to complete their national service journey.</p>
                    <div class="hero-actions"><a class="button button-primary" href="#components">Explore NSTP <span aria-hidden="true">↓</span></a><a class="button button-light" href="#activities">View activities</a><a class="text-link" href="{{ route('login') }}">Open Student Portal <span aria-hidden="true">↗</span></a></div>
                    <dl class="hero-facts" aria-label="NSTP highlights"><div><dt>3</dt><dd>Program<br>components</dd></div><div><dt>1</dt><dd>Shared mission<br>of service</dd></div><div><dt>24/7</dt><dd>Portal<br>access</dd></div></dl>
                </div>
                <div class="hero-media" aria-label="TAU NSTP in action">
                    <figure class="hero-photo hero-photo-main"><img src="https://tau.edu.ph/images/Content/2026/70.jpg" alt="TAU NSTP students participating in gender-responsive first-aid training" fetchpriority="high"><figcaption><span>In action</span> Emergency-response skills training</figcaption></figure>
                    <figure class="hero-photo hero-photo-small"><img src="https://tau.edu.ph/images/Content/2025/613.png" alt="TAU ROTC cadets at the opening of training" fetchpriority="high"><figcaption>Discipline in service</figcaption></figure>
                    <div class="hero-stamp" aria-hidden="true"><strong>NSTP</strong><span>TAU · EST. 1945</span></div>
                </div>
            </div>
            <div class="hero-ribbon" aria-hidden="true"><span>MAKABAYAN</span><span>MAKATAO</span><span>MAKAKALIKASAN</span><span>MAKADIYOS</span></div>
        </section>

        <section class="section about-section" id="about" aria-labelledby="about-title">
            <div class="page-shell about-grid">
                <div class="section-heading"><p class="eyebrow dark"><span></span> About the program</p><h2 id="about-title">Learning that moves beyond the classroom.</h2></div>
                <div class="about-copy"><p class="lead-paragraph">The National Service Training Program develops civic consciousness, defense preparedness, and a genuine commitment to nation-building among Filipino youth.</p><p>At TAU, students choose a path that matches how they want to serve—through military training, community welfare initiatives, or literacy education. Smart NSTP supports that journey from registration and enrollment to attendance, assessment, community engagement, and completion.</p><div class="law-note"><span>Republic Act No. 9163</span><p>The NSTP Act of 2001 established ROTC, CWTS, and LTS as the program's three components.</p></div></div>
            </div>
        </section>

        <section class="section components-section" id="components" aria-labelledby="components-title">
            <div class="page-shell">
                <div class="section-intro"><div><p class="eyebrow dark"><span></span> Choose your path</p><h2 id="components-title">Three components.<br>One call to serve.</h2></div><p>Each component builds a different kind of readiness while sharing the same goal: citizens prepared to contribute meaningfully to the country.</p></div>
                <div class="component-tabs" role="tablist" aria-label="NSTP components"><button type="button" role="tab" aria-selected="true" aria-controls="component-rotc" id="tab-rotc" data-component-tab="rotc"><span>01</span>ROTC</button><button type="button" role="tab" aria-selected="false" aria-controls="component-cwts" id="tab-cwts" data-component-tab="cwts"><span>02</span>CWTS</button><button type="button" role="tab" aria-selected="false" aria-controls="component-lts" id="tab-lts" data-component-tab="lts"><span>03</span>LTS</button></div>
                <div class="component-panels">
                    <article class="component-panel is-active" id="component-rotc" role="tabpanel" aria-labelledby="tab-rotc" data-component-panel="rotc"><div class="component-image"><img src="https://tau.edu.ph/images/Content/2025/613.png" alt="TAU ROTC cadets standing in formation" loading="lazy"><img class="component-mark" src="{{ asset('images/branding/rotc-logo.png') }}" alt=""></div><div class="component-content"><p class="component-code">Reserve Officers' Training Corps</p><h3>Lead with discipline and readiness.</h3><p>ROTC provides military education and training that prepares students for national defense and public-service leadership.</p><ul><li>Military discipline and drills</li><li>Leadership and teamwork</li><li>Disaster and emergency readiness</li></ul><a class="button button-outline" href="{{ route('register') }}">Begin registration <span aria-hidden="true">→</span></a></div></article>
                    <article class="component-panel" id="component-cwts" role="tabpanel" aria-labelledby="tab-cwts" data-component-panel="cwts" hidden><div class="component-image"><img src="https://tau.edu.ph/images/Content/2026/64.png" alt="TAU community participating in a university-wide clean-up drive" loading="lazy"><img class="component-mark" src="{{ asset('images/branding/cwts-logo.png') }}" alt=""></div><div class="component-content"><p class="component-code">Civic Welfare Training Service</p><h3>Turn compassion into community action.</h3><p>CWTS equips students to design and carry out activities that improve health, education, environment, safety, and community welfare.</p><ul><li>Community needs assessment</li><li>Environmental and health initiatives</li><li>Project planning and implementation</li></ul><a class="button button-outline" href="{{ route('register') }}">Begin registration <span aria-hidden="true">→</span></a></div></article>
                    <article class="component-panel" id="component-lts" role="tabpanel" aria-labelledby="tab-lts" data-component-panel="lts" hidden><div class="component-image lts-image"><img src="https://www.tau.edu.ph/images/2025/Content/509.png" alt="Official TAU NSTP orientation announcement" loading="lazy"><img class="component-mark" src="{{ asset('images/branding/lts-logo.png') }}" alt=""></div><div class="component-content"><p class="component-code">Literacy Training Service</p><h3>Open doors through literacy.</h3><p>LTS trains students to teach literacy and numeracy skills to children, out-of-school youth, and other community members who need support.</p><ul><li>Learning activity design</li><li>Literacy and numeracy facilitation</li><li>Inclusive educational outreach</li></ul><a class="button button-outline" href="{{ route('register') }}">Begin registration <span aria-hidden="true">→</span></a></div></article>
                </div>
            </div>
        </section>

        <section class="section activities-section" id="activities" aria-labelledby="activities-title">
            <div class="page-shell">
                <div class="section-intro activities-intro"><div><p class="eyebrow light"><span></span> Field notes</p><h2 id="activities-title">Service in motion.</h2></div><p>Explore official TAU stories from training grounds, campus initiatives, and community-centered activities.</p></div>
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

        <section class="section announcements-section" id="announcements" aria-labelledby="announcements-title">
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

        <section class="section services-section" id="services" aria-labelledby="services-title"><div class="page-shell services-grid"><div class="services-heading"><p class="eyebrow light"><span></span> Online services</p><h2 id="services-title">Start here.<br>Stay on track.</h2><p>Use the public services below or sign in for your complete student workspace.</p></div><div class="service-links"><a href="{{ route('register') }}"><span class="service-number">01</span><span><strong>Student registration</strong><small>Submit your NSTP application and requirements.</small></span><span aria-hidden="true">↗</span></a><a href="{{ route('login') }}"><span class="service-number">02</span><span><strong>Student portal</strong><small>View attendance, assessments, documents, and updates.</small></span><span aria-hidden="true">↗</span></a><a href="{{ route('serial-numbers.verify') }}"><span class="service-number">03</span><span><strong>Serial number verifier</strong><small>Verify an issued NSTP completion serial number.</small></span><span aria-hidden="true">↗</span></a><a href="{{ route('password.request') }}"><span class="service-number">04</span><span><strong>Account recovery</strong><small>Request help accessing an existing account.</small></span><span aria-hidden="true">↗</span></a></div></div></section>

        <section class="section faq-section" id="faqs" aria-labelledby="faq-title"><div class="page-shell faq-grid"><div><p class="eyebrow dark"><span></span> Frequently asked</p><h2 id="faq-title">Questions before you begin?</h2><p>Here are quick answers to the essentials. Your assigned facilitator or the NSTP Office can help with concerns specific to your enrollment.</p></div><div class="faq-list"><details><summary>Who must take NSTP?<span aria-hidden="true">+</span></summary><p>Students covered by the NSTP Act complete one of its three components—ROTC, CWTS, or LTS—as part of their degree requirements.</p></details><details><summary>How do I choose an NSTP component?<span aria-hidden="true">+</span></summary><p>Start a student registration and follow the component-selection period announced by the NSTP Office. Availability may depend on the active academic term and section capacity.</p></details><details><summary>What should I prepare for registration?<span aria-hidden="true">+</span></summary><p>Prepare your enrollment information, Certificate of Registration, a formal photo, and any component-specific supporting document requested by the system.</p></details><details><summary>Where can I check attendance and submissions?<span aria-hidden="true">+</span></summary><p>Sign in to the Student Portal to view your section, attendance history, assigned activities, assessments, submitted documents, and completion progress.</p></details><details><summary>Can I verify an NSTP serial number online?<span aria-hidden="true">+</span></summary><p>Yes. Use the public Serial Number Verifier and enter the serial number exactly as issued by the NSTP Office.</p></details></div></div></section>

        <section class="contact-section" id="contact" aria-labelledby="contact-title"><div class="page-shell contact-grid"><div><p class="eyebrow light"><span></span> Connect with us</p><h2 id="contact-title">Ready to serve?</h2><p>Visit the NSTP Office at Tarlac Agricultural University or use the Student Portal for enrollment-specific concerns.</p></div><div class="contact-actions"><a class="button button-gold" href="{{ route('register') }}">Register for NSTP <span aria-hidden="true">→</span></a><a class="button button-ghost" href="https://www.tau.edu.ph" target="_blank" rel="noopener">Visit TAU website ↗</a></div></div></section>
    </main>

    <footer class="site-footer"><div class="page-shell footer-grid"><div class="footer-brand"><img src="{{ asset('images/branding/tau-logo.png') }}" alt=""><div><strong>Tarlac Agricultural University</strong><span>Smart NSTP Management Platform</span></div></div><div><strong>Quick links</strong><a href="#about">About NSTP</a><a href="#components">Components</a><a href="#activities">Activities</a></div><div><strong>Student services</strong><a href="{{ route('register') }}">Register</a><a href="{{ route('login') }}">Portal login</a><a href="{{ route('serial-numbers.verify') }}">Verify serial number</a></div><div><strong>Campus</strong><span>Malacampa, Camiling</span><span>Tarlac, Philippines</span><a href="https://www.tau.edu.ph" target="_blank" rel="noopener">www.tau.edu.ph ↗</a></div></div><div class="page-shell footer-bottom"><span>© {{ date('Y') }} Tarlac Agricultural University. All rights reserved.</span><a href="#home">Back to top ↑</a></div></footer>
</body>
</html>
