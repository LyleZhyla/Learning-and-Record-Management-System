<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemSetting extends Model
{
    public const LANDING_PAGE_DEFAULTS = [
        'hero_video_url' => 'https://d8j0ntlcm91z4.cloudfront.net/user_38xzZboKViGWJOttwIXH07lWA1P/hf_20261005_182346_a590ff3b-72e5-41ce-8f69-a8c823eaecaa.mp4',
        'hero_poster_url' => 'https://tau.edu.ph/images/Content/2026/70.jpg',
        'hero_brand' => 'Smart NSTP',
        'hero_line_1' => 'Empowering Students.',
        'hero_line_2' => 'Serving Communities.',
        'hero_line_3' => 'Building Future Leaders.',
        'hero_lead_1' => 'Purposeful training, civic responsibility, and community engagement.',
        'hero_lead_2' => 'One digital platform for every TAU NSTP journey.',
        'about_eyebrow' => 'About the program',
        'about_title' => 'National Service Training Program',
        'about_lead' => 'The National Service Training Program develops civic consciousness, defense preparedness, and a genuine commitment to nation-building among Filipino youth.',
        'about_body' => 'At TAU, students choose a path that matches how they want to serve—through military training, community welfare initiatives, or literacy education. Smart NSTP supports that journey from registration and enrollment to attendance, assessment, community engagement, and completion.',
        'about_law_title' => 'Republic Act No. 9163',
        'about_law_body' => "The NSTP Act of 2001 established ROTC, CWTS, and LTS as the program's three components.",
        'components_eyebrow' => 'Program options',
        'components_title' => 'NSTP Program Components',
        'components_intro' => 'Students complete one of three components according to their chosen area of national and community service.',
        'rotc_title' => 'Military training and national defense readiness',
        'rotc_body' => 'ROTC provides military education and training that prepares students for national defense and public-service leadership.',
        'cwts_title' => 'Community welfare and civic engagement',
        'cwts_body' => 'CWTS equips students to design and carry out activities that improve health, education, environment, safety, and community welfare.',
        'lts_title' => 'Literacy and numeracy education',
        'lts_body' => 'LTS trains students to teach literacy and numeracy skills to children, out-of-school youth, and other community members who need support.',
        'activities_eyebrow' => 'News and media',
        'activities_title' => 'NSTP Activities and Updates',
        'activities_intro' => 'Explore official TAU stories from training grounds, campus initiatives, and community-centered activities.',
        'services_eyebrow' => 'Online services',
        'services_title' => 'Public Services and Requests',
        'services_intro' => 'Access NSTP registration, records verification, assistance requests, and the student portal.',
        'snapie_title' => 'Need help choosing a service?',
        'snapie_body' => 'SNAPIE identifies the appropriate online request for your concern.',
        'faq_eyebrow' => 'Student information',
        'faq_title' => 'Frequently Asked Questions',
        'faq_intro' => 'For enrollment- or section-specific concerns, contact your assigned facilitator or the NSTP Office.',
        'faq_1_question' => 'Who must take NSTP?',
        'faq_1_answer' => 'Students covered by the NSTP Act complete one of its three components—ROTC, CWTS, or LTS—as part of their degree requirements.',
        'faq_2_question' => 'How do I choose an NSTP component?',
        'faq_2_answer' => 'Start a student registration and follow the component-selection period announced by the NSTP Office. Availability may depend on the active academic term and section capacity.',
        'faq_3_question' => 'What should I prepare for registration?',
        'faq_3_answer' => 'Prepare your enrollment information, Certificate of Registration, a formal photo, and any component-specific supporting document requested by the system.',
        'faq_4_question' => 'Where can I check attendance and submissions?',
        'faq_4_answer' => 'Sign in to the Student Portal to view your section, attendance history, assigned activities, assessments, submitted documents, and completion progress.',
        'faq_5_question' => 'Can I verify an NSTP serial number online?',
        'faq_5_answer' => 'Yes. Use the public Serial Number Verifier and enter the serial number exactly as issued by the NSTP Office.',
        'contact_eyebrow' => 'Contact',
        'contact_title' => 'NSTP Office',
        'contact_body' => 'Visit the NSTP Office at Tarlac Agricultural University or use the Student Portal for enrollment-specific concerns.',
    ];

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value', 'updated_by'];

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function landingPageContent(): array
    {
        $stored = json_decode((string) static::query()->where('key', 'landing_page_content')->value('value'), true);

        return array_replace(self::LANDING_PAGE_DEFAULTS, is_array($stored) ? array_intersect_key($stored, self::LANDING_PAGE_DEFAULTS) : []);
    }

    public static function inactivityTimeoutMinutes(): int
    {
        return max(1, min(1440, (int) static::query()
            ->where('key', 'inactivity_timeout_minutes')
            ->value('value') ?: 30));
    }

    public static function componentSelectionIsOpen(): bool
    {
        return static::query()
            ->where('key', 'component_selection_open')
            ->value('value') !== '0';
    }

    public static function studentRegistrationIsOpen(): bool
    {
        return static::query()->where('key', 'student_registration_open')->value('value') !== '0';
    }

    public static function studentRegistrationAcademicYear(): string
    {
        $startYear = now()->month >= 6 ? now()->year : now()->year - 1;

        return static::query()->where('key', 'student_registration_academic_year')->value('value')
            ?: $startYear.'-'.($startYear + 1);
    }

    public static function studentRegistrationSemester(): string
    {
        return static::query()->where('key', 'student_registration_semester')->value('value')
            ?: (now()->month >= 6 ? 'first' : 'second');
    }

    public static function defaultPassingPercentage(): float
    {
        return max(1, min(99.99, (float) (static::query()
            ->where('key', 'default_passing_percentage')
            ->value('value') ?: 75)));
    }

    public static function defaultPassingGrade(): float
    {
        return max(1.01, min(4.99, (float) (static::query()
            ->where('key', 'default_passing_grade')
            ->value('value') ?: 3)));
    }

    public static function requiredDocumentsAreEnforced(): bool
    {
        return static::query()
            ->where('key', 'required_documents_enforced')
            ->value('value') !== '0';
    }
}
