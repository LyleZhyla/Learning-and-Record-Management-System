<?php

use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\AiLearningRecommendation;
use App\Models\Announcement;
use App\Models\Assessment;
use App\Models\AssessmentSubmission;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\BackupRestorationDrill;
use App\Models\ChatGroup;
use App\Models\ChatGroupMessage;
use App\Models\ChatMessage;
use App\Models\ChedApplication;
use App\Models\ChedApplicationStatusHistory;
use App\Models\CommunityProject;
use App\Models\CommunityProjectActivity;
use App\Models\CommunityProjectDocument;
use App\Models\ComponentAssessmentSetting;
use App\Models\DataPrivacyRequest;
use App\Models\DocumentForm;
use App\Models\DocumentSubmission;
use App\Models\EvaluationResponse;
use App\Models\FacilitatorAccomplishmentReport;
use App\Models\FacilitatorHonorarium;
use App\Models\FacilitatorRequirement;
use App\Models\FacilitatorRequirementSubmission;
use App\Models\GradingCategory;
use App\Models\GradingSetting;
use App\Models\LandingPageMedia;
use App\Models\LearningMaterial;
use App\Models\NotificationRule;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\NstpSerialNumberRelease;
use App\Models\NstpServiceRequest;
use App\Models\NstpStudentSerialNumber;
use App\Models\OmrScanResult;
use App\Models\OmrSheet;
use App\Models\PrivacyConsent;
use App\Models\PrivacyIncident;
use App\Models\PrivacyNotice;
use App\Models\ProjectTask;
use App\Models\RetentionPolicy;
use App\Models\ReviewCategory;
use App\Models\Role;
use App\Models\RubricTemplate;
use App\Models\ScheduleSetting;
use App\Models\SectionSchedule;
use App\Models\SensitiveFieldAccessLog;
use App\Models\StudentNotification;
use App\Models\StudentProfile;
use App\Models\StudentRegistration;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStepCompletion;

return [
    'models' => [
        User::class,
        Role::class,
        SystemSetting::class,
        NstpComponent::class,
        NstpSection::class,
        NstpEnrollment::class,
        AttendanceSession::class,
        AttendanceRecord::class,
        LearningMaterial::class,
        Assessment::class,
        AssessmentSubmission::class,
        StudentRegistration::class,
        StudentProfile::class,
        DocumentForm::class,
        DocumentSubmission::class,
        ReviewCategory::class,
        WorkflowDefinition::class,
        WorkflowStepCompletion::class,
        NotificationRule::class,
        ComponentAssessmentSetting::class,
        ChedApplication::class,
        ChedApplicationStatusHistory::class,
        CommunityProject::class,
        CommunityProjectActivity::class,
        CommunityProjectDocument::class,
        ProjectTask::class,
        EvaluationResponse::class,
        FacilitatorRequirement::class,
        FacilitatorRequirementSubmission::class,
        FacilitatorHonorarium::class,
        FacilitatorAccomplishmentReport::class,
        NstpSerialNumberRelease::class,
        NstpStudentSerialNumber::class,
        NstpServiceRequest::class,
        ScheduleSetting::class,
        SectionSchedule::class,
        GradingCategory::class,
        GradingSetting::class,
        Announcement::class,
        LandingPageMedia::class,
        RubricTemplate::class,
        AiLearningRecommendation::class,
        StudentNotification::class,
        ChatMessage::class,
        ChatGroup::class,
        ChatGroupMessage::class,
        AiChatConversation::class,
        AiChatMessage::class,
        OmrSheet::class,
        OmrScanResult::class,
        AuditLog::class,
        PrivacyNotice::class,
        PrivacyConsent::class,
        RetentionPolicy::class,
        DataPrivacyRequest::class,
        SensitiveFieldAccessLog::class,
        PrivacyIncident::class,
        BackupRestorationDrill::class,
    ],
];
