(function () {
    const tourConfigurations = [
        {
            root: '[data-admin-tour-root]',
            storagePrefix: 'snapie.adminTour',
            targetAttribute: 'data-admin-tour',
            pageTitleSelector: '[data-admin-tour-page-title]',
            startSelector: '[data-start-admin-tour]',
            steps: [
                { target: 'dashboard', title: 'Your command center', text: 'Start here to monitor student enrollment, attendance trends, sections, and current-term activity.' },
                { target: 'staff', title: 'Build your NSTP team', text: 'Create Super Admin, NSTP Admin, coordinator, and facilitator accounts. Assign only the access each person needs.' },
                { target: 'students', title: 'Manage student access', text: 'Review registrations, import student lists, send account access, and download student QR codes.' },
                { target: 'components', title: 'Prepare NSTP components', text: 'Configure CWTS, LTS, and ROTC capacities and control when component selection is open.' },
                { target: 'sectioning', title: 'Organize students into classes', text: 'Create sections, assign facilitators, check capacity, and run automatic sectioning.' },
                { target: 'scheduling', title: 'Set conflict-aware schedules', text: 'Generate schedules, review conflicts, and adjust each section before operations begin.' },
                { target: 'attendance', title: 'Run attendance sessions', text: 'Open QR attendance sessions, scan student codes, review records, and close completed sessions.' },
                { target: 'reports', title: 'Create official reports', text: 'Filter institution-wide data and export the results to Excel, Word, PDF, or print view.' },
                { target: 'backup', title: 'Protect the database', text: 'Download or archive a restorable backup before bulk updates and end-of-term maintenance.' },
                { target: 'logs', title: 'Keep actions accountable', text: 'Review the audit trail to see who performed important changes and when they happened.' },
                { target: 'ai', title: 'Ask SNAPIE AI', text: 'Use the assistant for NSTP and system guidance. Never enter passwords, API keys, or unnecessary private data.' },
                { target: 'guide', title: 'Return to the full guide anytime', text: 'Open the written workflow, role boundaries, and safety checklist whenever you need a reference.' },
            ],
        },
        {
            root: '[data-student-tour-root]',
            storagePrefix: 'snapie.studentTour',
            targetAttribute: 'data-student-tour',
            pageTitleSelector: '[data-student-tour-page-title]',
            startSelector: '[data-start-student-tour]',
            steps: [
                { target: 'dashboard', title: 'Your student dashboard', text: 'Start here to see your NSTP enrollment, attendance, learning materials, pending assessments, and current grade.' },
                { target: 'component', title: 'Choose your NSTP component', text: 'Review your CWTS, LTS, or ROTC selection and check whether it is pending, approved, or already assigned to a section.' },
                { target: 'attendance', title: 'Track your attendance', text: 'Use the attendance page to view your QR details and review Present, Late, and Absent records.' },
                { target: 'materials', title: 'Open learning materials', text: 'Find the files and resources published for your NSTP component and assigned section.' },
                { target: 'recommendations', title: 'Build a personalized learning path', text: 'Ask AI to prioritize your authorized materials using your study goal, available time, assessments, and released progress.' },
                { target: 'proposal', title: 'Improve a project proposal', text: 'Describe your community project idea and receive structured AI guidance. Your facilitator still makes every official decision.' },
                { target: 'assessments', title: 'Complete assessments', text: 'Check instructions and deadlines, open each activity, and submit your work before it closes.' },
                { target: 'grades', title: 'Review your grades', text: 'See graded activities, scores, and your computed performance based on released results.' },
                { target: 'reports', title: 'Download your records', text: 'Open your personal student reports and download the available official summaries.' },
                { target: 'announcements', title: 'Follow announcements', text: 'Read official updates from the NSTP team so you do not miss schedules, deadlines, or instructions.' },
                { target: 'messages', title: 'Use authorized messages', text: 'Contact the NSTP staff and groups available to your account, and check unread conversations.' },
                { target: 'ai', title: 'Ask SNAPIE AI', text: 'Use the assistant for NSTP and system guidance. Never enter passwords, API keys, or unnecessary private information.' },
                { target: 'profile', title: 'Protect your account', text: 'Keep your student details accurate and use a strong, private password for your account.' },
                { target: 'guide', title: 'Return to the full guide anytime', text: 'Open the written student workflow and safety checklist whenever you need a quick reference.' },
            ],
        },
        {
            root: '[data-nstp-admin-tour-root]',
            storagePrefix: 'snapie.nstpAdminTour',
            targetAttribute: 'data-nstp-admin-tour',
            pageTitleSelector: '[data-nstp-admin-tour-page-title]',
            startSelector: '[data-start-nstp-admin-tour]',
            steps: [
                { target: 'dashboard', title: 'Your operations dashboard', text: 'Start here to monitor active students, staff, components, and records that still need assignments.' },
                { target: 'staff', title: 'Review the operational team', text: 'Check coordinators and facilitators, then confirm that each account has the correct component assignment.' },
                { target: 'students', title: 'Manage student access', text: 'Add or import students, distribute account access, and review the QR identity assigned to each student.' },
                { target: 'registrations', title: 'Review student registrations', text: 'Validate submitted details and documents before accepting or rejecting a registration.' },
                { target: 'components', title: 'Prepare NSTP components', text: 'Review CWTS, LTS, and ROTC settings and control when students may submit component selections.' },
                { target: 'sectioning', title: 'Organize sections', text: 'Assign students and facilitators, check capacity, and complete sectioning for the active term.' },
                { target: 'scheduling', title: 'Coordinate schedules', text: 'Generate schedules, review required hours, and resolve facilitator conflicts.' },
                { target: 'attendance', title: 'Oversee attendance', text: 'Monitor QR attendance sessions and review records that need authorized correction.' },
                { target: 'assessments', title: 'Oversee assessment records', text: 'Check assessments, submissions, grading structures, and released results.' },
                { target: 'reports', title: 'Create verified reports', text: 'Filter institution-wide records and confirm the active term before exporting official summaries.' },
                { target: 'ai', title: 'Ask SNAPIE AI', text: 'Use the assistant for workflow guidance without sharing credentials or unnecessary private student data.' },
                { target: 'guide', title: 'Return to the full guide anytime', text: 'Open the complete NSTP Administrator workflow and safety checklist whenever you need a reference.' },
            ],
        },
        {
            root: '[data-coordinator-tour-root]',
            storagePrefix: 'snapie.coordinatorTour',
            targetAttribute: 'data-coordinator-tour',
            pageTitleSelector: '[data-coordinator-tour-page-title]',
            startSelector: '[data-start-coordinator-tour]',
            steps: [
                { target: 'dashboard', title: 'Your component dashboard', text: 'Start here to monitor students, facilitators, sections, attendance, and performance in your assigned component.' },
                { target: 'components', title: 'Confirm your component scope', text: 'Review the CWTS, LTS, or ROTC component assigned to your coordinator account.' },
                { target: 'accounts', title: 'Review facilitators and students', text: 'Inspect the people assigned to your component and identify records that need administrative correction.' },
                { target: 'sections', title: 'Monitor sections and facilitators', text: 'Check capacity, facilitator assignments, enrollment, and section status.' },
                { target: 'scheduling', title: 'Review class schedules', text: 'Verify required hours and resolve schedule conflicts before classes proceed.' },
                { target: 'attendance', title: 'Monitor attendance', text: 'Review attendance sessions, QR records, and unexpected late or absent entries.' },
                { target: 'omr', title: 'Scan answer sheets carefully', text: 'Use the correct assessment and confirm the captured answers before saving results.' },
                { target: 'performance', title: 'Track student performance', text: 'Review grade completion, attendance signals, and students who may need support.' },
                { target: 'reports', title: 'Export component reports', text: 'Confirm the component and term filters before sharing official summaries.' },
                { target: 'ai', title: 'Ask SNAPIE AI', text: 'Use the assistant for system explanations, then verify all official decisions against authorized records.' },
                { target: 'guide', title: 'Return to the full guide anytime', text: 'Open the complete Coordinator workflow and oversight checklist whenever you need a reference.' },
            ],
        },
        {
            root: '[data-facilitator-tour-root]',
            storagePrefix: 'snapie.facilitatorTour',
            targetAttribute: 'data-facilitator-tour',
            pageTitleSelector: '[data-facilitator-tour-page-title]',
            startSelector: '[data-start-facilitator-tour]',
            steps: [
                { target: 'dashboard', title: 'Your teaching dashboard', text: 'Start here to review assigned sections, students, attendance activity, and ungraded work.' },
                { target: 'students', title: 'Review your students', text: 'Check the official class list and open the records available for each assigned student.' },
                { target: 'attendance', title: 'Run attendance sessions', text: 'Create the correct section session, display its QR code, and review time-in, time-out, late, and absent records.' },
                { target: 'materials', title: 'Publish learning materials', text: 'Upload or link resources and verify that they reach only the intended component or section.' },
                { target: 'assessments', title: 'Create and review assessments', text: 'Set clear instructions, deadlines, rubrics, and scores, then review every student submission.' },
                { target: 'omr', title: 'Scan paper assessments', text: 'Align answer sheets carefully and confirm captured answers before recording results.' },
                { target: 'grades', title: 'Validate the gradebook', text: 'Review every score and weighted total before treating a student grade as final.' },
                { target: 'reports', title: 'Prepare class reports', text: 'Use the correct section and term filters before exporting or printing records.' },
                { target: 'messages', title: 'Communicate with your classes', text: 'Use authorized individual and group conversations without exposing private student information.' },
                { target: 'ai', title: 'Ask SNAPIE AI', text: 'Use AI as advisory support and keep the facilitator responsible for every official academic decision.' },
                { target: 'guide', title: 'Return to the full guide anytime', text: 'Open the complete Facilitator workflow and safety checklist whenever you need a reference.' },
            ],
        },
    ];
    const tour = tourConfigurations.find((configuration) => document.querySelector(configuration.root));

    if (!tour) {
        return;
    }

    const { storagePrefix, targetAttribute, pageTitleSelector, startSelector, steps } = tour;
    const activeKey = `${storagePrefix}.active`;
    const stepKey = `${storagePrefix}.step`;
    const modeKey = `${storagePrefix}.mode`;

    let currentStep = 0;
    let backdrop;
    let highlight;
    let dialog;
    let title;
    let description;
    let progress;
    let backButton;
    let nextButton;
    let openButton;
    let activeTarget;
    let currentMode = 'menu';

    function buildTour() {
        if (dialog) {
            return;
        }

        backdrop = document.createElement('div');
        backdrop.className = 'admin-tour-backdrop';
        backdrop.setAttribute('aria-hidden', 'true');

        highlight = document.createElement('div');
        highlight.className = 'admin-tour-highlight';
        highlight.setAttribute('aria-hidden', 'true');

        dialog = document.createElement('section');
        dialog.className = 'admin-tour-dialog';
        dialog.setAttribute('role', 'dialog');
        dialog.setAttribute('aria-modal', 'true');
        dialog.setAttribute('aria-labelledby', 'admin-tour-title');
        dialog.innerHTML = `
            <div class="admin-tour-dialog-heading">
                <span class="admin-tour-progress"></span>
                <button class="admin-tour-close" type="button" aria-label="Exit guided tour">×</button>
            </div>
            <h2 id="admin-tour-title"></h2>
            <p class="admin-tour-description"></p>
            <div class="admin-tour-actions">
                <button class="admin-tour-back" type="button">Back</button>
                <a class="admin-tour-open" href="#">Open page</a>
                <button class="admin-tour-next" type="button">Next</button>
            </div>`;

        document.body.append(backdrop, highlight, dialog);
        title = dialog.querySelector('#admin-tour-title');
        description = dialog.querySelector('.admin-tour-description');
        progress = dialog.querySelector('.admin-tour-progress');
        backButton = dialog.querySelector('.admin-tour-back');
        nextButton = dialog.querySelector('.admin-tour-next');
        openButton = dialog.querySelector('.admin-tour-open');

        dialog.querySelector('.admin-tour-close').addEventListener('click', stopTour);
        backButton.addEventListener('click', () => {
            if (currentMode === 'page') {
                showStep(currentStep);
                return;
            }

            showStep(currentStep - 1);
        });
        nextButton.addEventListener('click', () => {
            if (currentStep === steps.length - 1) {
                stopTour();
                return;
            }

            showStep(currentStep + 1);
        });
        openButton.addEventListener('click', () => {
            sessionStorage.setItem(activeKey, 'true');
            sessionStorage.setItem(stepKey, String(currentStep));
            sessionStorage.setItem(modeKey, 'page');
        });
    }

    function placeTour() {
        if (!activeTarget || !dialog || dialog.hidden) {
            return;
        }

        const targetBox = activeTarget.getBoundingClientRect();
        const gap = 14;
        const padding = 5;

        Object.assign(highlight.style, {
            top: `${targetBox.top - padding}px`,
            left: `${targetBox.left - padding}px`,
            width: `${targetBox.width + (padding * 2)}px`,
            height: `${targetBox.height + (padding * 2)}px`,
        });

        if (window.innerWidth <= 760) {
            dialog.style.top = 'auto';
            dialog.style.right = '14px';
            dialog.style.bottom = '14px';
            dialog.style.left = '14px';
            return;
        }

        const dialogBox = dialog.getBoundingClientRect();
        const left = Math.min(targetBox.right + gap, window.innerWidth - dialogBox.width - 18);
        const top = Math.max(18, Math.min(targetBox.top, window.innerHeight - dialogBox.height - 18));

        dialog.style.top = `${top}px`;
        dialog.style.right = 'auto';
        dialog.style.bottom = 'auto';
        dialog.style.left = `${left}px`;
    }

    function showStep(index) {
        buildTour();
        currentMode = 'menu';
        currentStep = Math.max(0, Math.min(index, steps.length - 1));
        const step = steps[currentStep];
        activeTarget = document.querySelector(`[${targetAttribute}="${step.target}"]`);

        if (!activeTarget) {
            stopTour();
            return;
        }

        document.body.classList.remove('sidebar-collapsed');
        document.getElementById('sidebar')?.classList.add('open');
        activeTarget.scrollIntoView({ block: 'center', behavior: 'smooth' });

        title.textContent = step.title;
        description.textContent = step.text;
        progress.textContent = `Step ${currentStep + 1} of ${steps.length}`;
        highlight.classList.remove('is-page');
        highlight.innerHTML = activeTarget.innerHTML;
        highlight.setAttribute('aria-label', `Highlighted menu: ${activeTarget.textContent.replace(/\s+/g, ' ').trim()}`);
        backButton.textContent = 'Back';
        backButton.disabled = currentStep === 0;
        nextButton.textContent = currentStep === steps.length - 1 ? 'Finish' : 'Next';
        openButton.hidden = false;
        openButton.href = activeTarget.href;
        openButton.textContent = `Open ${activeTarget.textContent.replace(/\s+/g, ' ').trim()}`;

        backdrop.hidden = false;
        highlight.hidden = false;
        dialog.hidden = false;
        document.body.classList.add('admin-tour-active');
        sessionStorage.setItem(activeKey, 'true');
        sessionStorage.setItem(stepKey, String(currentStep));
        sessionStorage.setItem(modeKey, 'menu');
        window.setTimeout(placeTour, 260);
        nextButton.focus({ preventScroll: true });
    }

    function showPageStep(index) {
        buildTour();
        currentMode = 'page';
        currentStep = Math.max(0, Math.min(index, steps.length - 1));
        const step = steps[currentStep];
        activeTarget = document.querySelector(pageTitleSelector);

        if (!activeTarget) {
            showStep(currentStep);
            return;
        }

        document.getElementById('sidebar')?.classList.remove('open');
        window.scrollTo({ top: 0, behavior: 'smooth' });

        const pageName = activeTarget.querySelector('h1')?.textContent.trim()
            || document.title.split('·')[0].trim();
        title.textContent = `${pageName} is now open`;
        description.textContent = `${step.text} The spotlight has moved to this page. Explore it now, then select Next module when you are ready to continue.`;
        progress.textContent = `Step ${currentStep + 1} of ${steps.length} · Page opened`;
        highlight.classList.add('is-page');
        highlight.innerHTML = activeTarget.innerHTML;
        highlight.setAttribute('aria-label', `Current page: ${pageName}`);
        backButton.disabled = false;
        backButton.textContent = 'Back to menu';
        nextButton.textContent = currentStep === steps.length - 1 ? 'Finish' : 'Next module';
        openButton.hidden = true;

        backdrop.hidden = false;
        highlight.hidden = false;
        dialog.hidden = false;
        document.body.classList.add('admin-tour-active');
        sessionStorage.setItem(activeKey, 'true');
        sessionStorage.setItem(stepKey, String(currentStep));
        sessionStorage.setItem(modeKey, 'page');
        window.setTimeout(placeTour, 260);
        nextButton.focus({ preventScroll: true });
    }

    function startTour() {
        showStep(0);
    }

    function stopTour() {
        sessionStorage.removeItem(activeKey);
        sessionStorage.removeItem(stepKey);
        sessionStorage.removeItem(modeKey);
        document.body.classList.remove('admin-tour-active');
        document.getElementById('sidebar')?.classList.remove('open');

        if (backdrop) backdrop.hidden = true;
        if (highlight) highlight.hidden = true;
        if (dialog) dialog.hidden = true;
        activeTarget = null;
    }

    document.querySelectorAll(startSelector).forEach((button) => {
        button.addEventListener('click', startTour);
    });

    window.addEventListener('resize', placeTour);
    document.querySelector('.main-nav')?.addEventListener('scroll', placeTour);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && document.body.classList.contains('admin-tour-active')) {
            stopTour();
        }
    });

    if (sessionStorage.getItem(activeKey) === 'true') {
        const savedStep = Number(sessionStorage.getItem(stepKey) || 0);

        if (sessionStorage.getItem(modeKey) === 'page') {
            showPageStep(savedStep);
        } else {
            showStep(savedStep);
        }
    }
})();
