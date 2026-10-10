<?php

use App\Http\Controllers\Admin\ArchiveController;
use App\Http\Controllers\Admin\ChedApplicationController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DatabaseBackupController;
use App\Http\Controllers\Admin\DirectoryExportController;
use App\Http\Controllers\Admin\DocumentFormController;
use App\Http\Controllers\Admin\NotificationRuleController;
use App\Http\Controllers\Admin\PolicyRequirementController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReviewCategoryController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SystemLogController;
use App\Http\Controllers\Admin\SystemSettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WorkflowController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PhilippineLocationController;
use App\Http\Controllers\Auth\StudentRegistrationController;
use App\Http\Controllers\CommunityProjectController;
use App\Http\Controllers\Coordinator\AccountController as CoordinatorAccountController;
use App\Http\Controllers\Coordinator\DashboardController as CoordinatorDashboardController;
use App\Http\Controllers\Coordinator\MonitoringController as CoordinatorMonitoringController;
use App\Http\Controllers\Coordinator\RotcApprovalController as CoordinatorRotcApprovalController;
use App\Http\Controllers\Coordinator\SerialNumberController as CoordinatorSerialNumberController;
use App\Http\Controllers\DocumentReviewController;
use App\Http\Controllers\EngagementAnalyticsController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\Facilitator\DashboardController as FacilitatorDashboardController;
use App\Http\Controllers\Facilitator\StudentController as FacilitatorStudentController;
use App\Http\Controllers\FacilitatorHonorariumController;
use App\Http\Controllers\FacilitatorRequirementController;
use App\Http\Controllers\Learning\AssessmentController;
use App\Http\Controllers\Learning\AttendanceController as ManagementAttendanceController;
use App\Http\Controllers\Learning\MaterialController;
use App\Http\Controllers\Learning\OmrScannerController;
use App\Http\Controllers\Learning\ScheduleController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NstpAdmin\AccountController as NstpAdminAccountController;
use App\Http\Controllers\NstpAdmin\AnnouncementController as NstpAdminAnnouncementController;
use App\Http\Controllers\NstpAdmin\ComponentController as NstpAdminComponentController;
use App\Http\Controllers\NstpAdmin\DashboardController as NstpAdminDashboardController;
use App\Http\Controllers\NstpAdmin\ProfileController as NstpAdminProfileController;
use App\Http\Controllers\NstpAdmin\SectionController as NstpAdminSectionController;
use App\Http\Controllers\NstpAdmin\SectioningController as NstpAdminSectioningController;
use App\Http\Controllers\Portal\AiAssistantController as PortalAiAssistantController;
use App\Http\Controllers\Portal\AnnouncementController as PortalAnnouncementController;
use App\Http\Controllers\Portal\MessageController as PortalMessageController;
use App\Http\Controllers\Portal\ProfileController as PortalProfileController;
use App\Http\Controllers\ProfilePhotoController;
use App\Http\Controllers\ProjectTaskController;
use App\Http\Controllers\RegistrationReviewController;
use App\Http\Controllers\SerialNumberVerificationController;
use App\Http\Controllers\Student\AttendanceController as StudentAttendanceController;
use App\Http\Controllers\Student\ComponentController as StudentComponentController;
use App\Http\Controllers\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\Student\DocumentController as StudentDocumentController;
use App\Http\Controllers\Student\LearningController as StudentLearningController;
use App\Http\Controllers\Student\LearningRecommendationController as StudentLearningRecommendationController;
use App\Http\Controllers\Student\ProfileController as StudentProfileController;
use App\Http\Controllers\Student\ProjectProposalGuideController as StudentProjectProposalGuideController;
use App\Http\Controllers\Student\ReportController as StudentReportController;
use App\Http\Controllers\Student\RequiredDocumentController as StudentRequiredDocumentController;
use App\Http\Controllers\StudentAccountController;
use App\Http\Controllers\StudentImportController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingPageController::class)->name('landing');

Route::get('/community-feedback/{communityProject}/{token}', [EvaluationController::class, 'communityForm'])->name('community-feedback.create');
Route::post('/community-feedback/{communityProject}/{token}', [EvaluationController::class, 'storeCommunity'])->middleware('throttle:10,1')->name('community-feedback.store');
Route::get('/verify/serial', SerialNumberVerificationController::class)->middleware('throttle:20,1')->name('serial-numbers.verify');

$learningManagementRoutes = function (): void {
    Route::get('/attendance', [ManagementAttendanceController::class, 'index'])->name('attendance.index');
    Route::get('/attendance/create', [ManagementAttendanceController::class, 'create'])->name('attendance.create');
    Route::post('/attendance', [ManagementAttendanceController::class, 'store'])->name('attendance.store');
    Route::get('/attendance/{attendance}', [ManagementAttendanceController::class, 'show'])->name('attendance.show');
    Route::patch('/attendance/{attendance}/scan-mode', [ManagementAttendanceController::class, 'updateScanMode'])->name('attendance.scan-mode');
    Route::post('/attendance/{attendance}/scan', [ManagementAttendanceController::class, 'scan'])->name('attendance.scan');
    Route::post('/attendance/{attendance}/mark', [ManagementAttendanceController::class, 'mark'])->name('attendance.mark');
    Route::patch('/attendance/{attendance}/close', [ManagementAttendanceController::class, 'close'])->name('attendance.close');
    Route::get('/materials', [MaterialController::class, 'index'])->name('materials.index');
    Route::get('/materials/create', [MaterialController::class, 'create'])->name('materials.create');
    Route::post('/materials', [MaterialController::class, 'store'])->name('materials.store');
    Route::get('/materials/{material}/download', [MaterialController::class, 'download'])->name('materials.download');
    Route::get('/assessments', [AssessmentController::class, 'index'])->name('assessments.index');
    Route::get('/assessments/create', [AssessmentController::class, 'create'])->name('assessments.create');
    Route::post('/assessments', [AssessmentController::class, 'store'])->name('assessments.store');
    Route::post('/assessments/rubric/ai-suggestion', [AssessmentController::class, 'suggestRubric'])->middleware('throttle:5,1')->name('assessments.rubric.suggest');
    Route::get('/assessments/{assessment}', [AssessmentController::class, 'show'])->name('assessments.show');
    Route::put('/assessments/{assessment}/rubric', [AssessmentController::class, 'updateRubric'])->name('assessments.rubric.update');
    Route::get('/assessments/{assessment}/submissions/{submission}/file', [AssessmentController::class, 'previewSubmissionFile'])->name('assessments.submissions.file');
    Route::get('/assessments/{assessment}/submissions/{submission}/download', [AssessmentController::class, 'downloadSubmissionFile'])->name('assessments.submissions.download');
    Route::post('/assessments/{assessment}/submissions/{submission}/ai-score', [AssessmentController::class, 'generateAiScore'])->middleware('throttle:5,1')->name('assessments.ai-score.generate');
    Route::put('/assessments/{assessment}/submissions/{submission}/ai-score/approve', [AssessmentController::class, 'approveAiScore'])->name('assessments.ai-score.approve');
    Route::put('/assessments/{assessment}/submissions/{submission}', [AssessmentController::class, 'grade'])->name('assessments.grade');
    Route::put('/assessments/{assessment}/students/{student}/score', [AssessmentController::class, 'scoreStudent'])->name('assessments.score');
    Route::get('/grades', [AssessmentController::class, 'grades'])->name('grades.index');
    Route::put('/grades/{section}/structure', [AssessmentController::class, 'updateGradeStructure'])->name('grades.structure');
    Route::delete('/grades/categories/{category}', [AssessmentController::class, 'destroyGradeCategory'])->name('grades.categories.destroy');
    Route::post('/grades/{section}/items', [AssessmentController::class, 'storeGradeItem'])->name('grades.items.store');
    Route::put('/grades/items/{assessment}', [AssessmentController::class, 'updateGradeItem'])->name('grades.items.update');
    Route::delete('/grades/items/{assessment}', [AssessmentController::class, 'destroyGradeItem'])->name('grades.items.destroy');
    Route::put('/grades/{section}/scores', [AssessmentController::class, 'updateGradeScore'])->name('grades.scores.update');
};

$omrScannerRoutes = function (): void {
    Route::get('/answer-sheet-scanner', [OmrScannerController::class, 'index'])->name('omr.index');
    Route::post('/answer-sheet-scanner', [OmrScannerController::class, 'store'])->name('omr.store');
    Route::get('/answer-sheet-scanner/{sheet}', [OmrScannerController::class, 'show'])->name('omr.show');
    Route::put('/answer-sheet-scanner/{sheet}/answer-key', [OmrScannerController::class, 'updateAnswerKey'])->name('omr.answer-key.update');
    Route::get('/answer-sheet-scanner/{sheet}/image', [OmrScannerController::class, 'image'])->name('omr.image');
    Route::get('/answer-sheet-scanner/{sheet}/print', [OmrScannerController::class, 'printable'])->name('omr.print');
    Route::post('/answer-sheet-scanner/{sheet}/grade', [OmrScannerController::class, 'grade'])->name('omr.grade');
};

$scheduleRoutes = function (): void {
    Route::get('/schedules', [ScheduleController::class, 'index'])->name('schedules.index');
    Route::put('/schedules/settings', [ScheduleController::class, 'updateSettings'])->name('schedules.settings.update');
    Route::post('/schedules/generate', [ScheduleController::class, 'generate'])->name('schedules.generate');
    Route::put('/schedules/sections/{section}', [ScheduleController::class, 'updateSection'])->name('schedules.sections.update');
};

$documentConfigurationRoutes = function (): void {
    Route::get('/document-forms', [DocumentFormController::class, 'index'])->name('document-forms.index');
    Route::get('/document-forms/create', [DocumentFormController::class, 'create'])->name('document-forms.create');
    Route::post('/document-forms', [DocumentFormController::class, 'store'])->name('document-forms.store');
    Route::get('/document-forms/{documentForm}/edit', [DocumentFormController::class, 'edit'])->name('document-forms.edit');
    Route::put('/document-forms/{documentForm}', [DocumentFormController::class, 'update'])->name('document-forms.update');
    Route::delete('/document-forms/{documentForm}', [DocumentFormController::class, 'destroy'])->name('document-forms.destroy');
    Route::get('/document-forms/{documentForm}/template', [DocumentFormController::class, 'downloadTemplate'])->name('document-forms.template');
};

$documentReviewRoutes = function (): void {
    Route::get('/document-reviews', [DocumentReviewController::class, 'index'])->name('document-reviews.index');
    Route::patch('/document-reviews/{documentSubmission}', [DocumentReviewController::class, 'update'])->name('document-reviews.update');
    Route::get('/document-reviews/{documentSubmission}/file', [DocumentReviewController::class, 'file'])->name('document-reviews.file');
    Route::get('/document-reviews/{documentSubmission}/download', [DocumentReviewController::class, 'download'])->name('document-reviews.download');
};

$reviewCategoryRoutes = function (): void {
    Route::get('/review-categories', [ReviewCategoryController::class, 'index'])->name('review-categories.index');
    Route::post('/review-categories', [ReviewCategoryController::class, 'store'])->name('review-categories.store');
    Route::put('/review-categories/{reviewCategory}', [ReviewCategoryController::class, 'update'])->name('review-categories.update');
    Route::delete('/review-categories/{reviewCategory}', [ReviewCategoryController::class, 'destroy'])->name('review-categories.destroy');
};

$workflowRoutes = function (): void {
    Route::get('/workflows', [WorkflowController::class, 'index'])->name('workflows.index');
    Route::put('/workflows/{workflow}', [WorkflowController::class, 'update'])->name('workflows.update');
};

$notificationRuleRoutes = function (): void {
    Route::get('/notification-rules', [NotificationRuleController::class, 'index'])->name('notification-rules.index');
    Route::put('/notification-rules/{notificationRule}', [NotificationRuleController::class, 'update'])->name('notification-rules.update');
};

$policyRequirementRoutes = function (): void {
    Route::get('/policies', [PolicyRequirementController::class, 'index'])->name('policies.index');
    Route::put('/policies', [PolicyRequirementController::class, 'update'])->name('policies.update');
};

$chedApplicationRoutes = function (): void {
    Route::get('/ched-applications', [ChedApplicationController::class, 'index'])->name('ched-applications.index');
    Route::post('/ched-applications', [ChedApplicationController::class, 'store'])->name('ched-applications.store');
    Route::get('/ched-applications/{chedApplication}', [ChedApplicationController::class, 'show'])->name('ched-applications.show');
    Route::put('/ched-applications/{chedApplication}/status', [ChedApplicationController::class, 'updateStatus'])->name('ched-applications.status');
    Route::get('/ched-applications/{chedApplication}/workbook', [ChedApplicationController::class, 'workbook'])->name('ched-applications.workbook');
};

$communityProjectRoutes = function (): void {
    Route::get('/community-projects', [CommunityProjectController::class, 'index'])->name('community-projects.index');
    Route::get('/community-projects/create', [CommunityProjectController::class, 'create'])->name('community-projects.create');
    Route::post('/community-projects', [CommunityProjectController::class, 'store'])->name('community-projects.store');
    Route::get('/community-projects/{communityProject}', [CommunityProjectController::class, 'show'])->name('community-projects.show');
    Route::get('/community-projects/{communityProject}/edit', [CommunityProjectController::class, 'edit'])->name('community-projects.edit');
    Route::put('/community-projects/{communityProject}', [CommunityProjectController::class, 'update'])->name('community-projects.update');
    Route::put('/community-projects/{communityProject}/approval', [CommunityProjectController::class, 'updateApproval'])->name('community-projects.approval');
    Route::put('/community-projects/{communityProject}/implementation', [CommunityProjectController::class, 'updateImplementation'])->name('community-projects.implementation');
    Route::post('/community-projects/{communityProject}/activities', [CommunityProjectController::class, 'storeActivity'])->name('community-projects.activities.store');
    Route::put('/community-projects/{communityProject}/activities/{activity}', [CommunityProjectController::class, 'updateActivity'])->name('community-projects.activities.update');
    Route::post('/community-projects/{communityProject}/documents', [CommunityProjectController::class, 'storeDocument'])->name('community-projects.documents.store');
    Route::get('/community-projects/{communityProject}/documents/{document}', [CommunityProjectController::class, 'downloadDocument'])->name('community-projects.documents.download');
    Route::delete('/community-projects/{communityProject}/documents/{document}', [CommunityProjectController::class, 'destroyDocument'])->name('community-projects.documents.destroy');
};

$projectTaskRoutes = function (): void {
    Route::get('/task-monitoring', [ProjectTaskController::class, 'index'])->name('project-tasks.index');
    Route::get('/community-projects/{communityProject}/tasks/create', [ProjectTaskController::class, 'create'])->name('project-tasks.create');
    Route::post('/community-projects/{communityProject}/tasks', [ProjectTaskController::class, 'store'])->name('project-tasks.store');
    Route::get('/task-monitoring/{projectTask}/edit', [ProjectTaskController::class, 'edit'])->name('project-tasks.edit');
    Route::put('/task-monitoring/{projectTask}', [ProjectTaskController::class, 'update'])->name('project-tasks.update');
    Route::put('/task-monitoring/{projectTask}/submit', [ProjectTaskController::class, 'submit'])->name('project-tasks.submit');
    Route::put('/task-monitoring/{projectTask}/review', [ProjectTaskController::class, 'review'])->name('project-tasks.review');
    Route::get('/task-monitoring/{projectTask}/evidence', [ProjectTaskController::class, 'evidence'])->name('project-tasks.evidence');
};

$evaluationRoutes = function (): void {
    Route::get('/evaluations', [EvaluationController::class, 'index'])->name('evaluations.index');
    Route::post('/evaluations/instructor', [EvaluationController::class, 'storeStudentInstructor'])->name('evaluations.student-instructor.store');
    Route::put('/evaluations/sections/{section}/students/{student}', [EvaluationController::class, 'storeInstructorStudent'])->name('evaluations.instructor-student.store');
    Route::put('/evaluations/community-projects/{communityProject}/survey', [EvaluationController::class, 'toggleCommunitySurvey'])->name('evaluations.community.toggle');
};

$facilitatorRequirementRoutes = function (): void {
    Route::get('/facilitator-requirements', [FacilitatorRequirementController::class, 'index'])->name('facilitator-requirements.index');
    Route::post('/facilitator-requirements', [FacilitatorRequirementController::class, 'storeRequirement'])->name('facilitator-requirements.store');
    Route::put('/facilitator-requirements/{facilitatorRequirement}', [FacilitatorRequirementController::class, 'updateRequirement'])->name('facilitator-requirements.update');
    Route::post('/facilitator-requirements/{facilitatorRequirement}/submit', [FacilitatorRequirementController::class, 'submit'])->name('facilitator-requirements.submit');
    Route::put('/facilitator-requirement-submissions/{submission}/review', [FacilitatorRequirementController::class, 'review'])->name('facilitator-requirements.review');
    Route::get('/facilitator-requirement-submissions/{submission}/download', [FacilitatorRequirementController::class, 'download'])->name('facilitator-requirements.download');
};

$honorariumViewerRoutes = function (): void {
    Route::get('/honoraria', [FacilitatorHonorariumController::class, 'index'])->name('honoraria.index');
};

$honorariumRequestRoutes = function (): void {
    Route::get('/honoraria/create', [FacilitatorHonorariumController::class, 'create'])->name('honoraria.create');
    Route::post('/honoraria', [FacilitatorHonorariumController::class, 'store'])->name('honoraria.store');
    Route::get('/honoraria/{honorarium}/payslip', [FacilitatorHonorariumController::class, 'payslip'])->name('honoraria.payslip');
};

$honorariumApprovalRoutes = function (): void {
    Route::put('/honoraria/{honorarium}/review', [FacilitatorHonorariumController::class, 'review'])->name('honoraria.review');
    Route::put('/honoraria/{honorarium}/disburse', [FacilitatorHonorariumController::class, 'disburse'])->name('honoraria.disburse');
};

$engagementAnalyticsRoutes = function (): void {
    Route::get('/engagement-analytics', EngagementAnalyticsController::class)->name('engagement-analytics.index');
};

Route::prefix('nstp-admin')->name('nstp_admin.')->middleware(['auth', 'nstp_admin'])->group(function () use ($learningManagementRoutes, $scheduleRoutes, $documentConfigurationRoutes, $documentReviewRoutes, $reviewCategoryRoutes, $workflowRoutes, $notificationRuleRoutes, $policyRequirementRoutes, $chedApplicationRoutes, $communityProjectRoutes, $projectTaskRoutes, $evaluationRoutes, $facilitatorRequirementRoutes, $honorariumViewerRoutes, $honorariumRequestRoutes, $honorariumApprovalRoutes, $engagementAnalyticsRoutes) {
    Route::get('/dashboard', NstpAdminDashboardController::class)->name('dashboard');
    Route::view('/system-guide', 'nstp_admin.system-guide')->name('system-guide');
    Route::get('/profile', [NstpAdminProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [NstpAdminProfileController::class, 'update'])->name('profile.update');
    Route::put('/password', [NstpAdminProfileController::class, 'updatePassword'])->name('password.update');
    Route::get('/messages/{contact?}', [PortalMessageController::class, 'index'])->name('messages.index');
    Route::post('/messages/{recipient}', [PortalMessageController::class, 'store'])->name('messages.store');
    Route::get('/accounts', [NstpAdminAccountController::class, 'index'])->name('accounts.index');
    Route::get('/students', [StudentAccountController::class, 'index'])->name('students.index');
    Route::get('/students/import', [StudentImportController::class, 'create'])->name('students.import.create');
    Route::post('/students/import', [StudentImportController::class, 'store'])->name('students.import.store');
    Route::get('/students/import/template', [StudentImportController::class, 'template'])->name('students.import.template');
    Route::get('/students/import/template/sql', [StudentImportController::class, 'sqlTemplate'])->name('students.import.sql-template');
    Route::post('/students/email-access', [StudentAccountController::class, 'bulkEmailAccess'])->middleware('throttle:3,1')->name('students.email-access');
    Route::get('/students/{student}/qr', [StudentAccountController::class, 'qr'])->name('students.qr');
    Route::get('/students/{student}/qr/download', [StudentAccountController::class, 'downloadQr'])->name('students.qr.download');
    Route::get('/students/export', [DirectoryExportController::class, 'students'])->name('students.export');
    Route::get('/registrations', [RegistrationReviewController::class, 'index'])->name('registrations.index');
    Route::patch('/registrations/settings', [SystemSettingController::class, 'updateRegistration'])->name('registrations.settings.update');
    Route::get('/registrations/{registration}/documents/{document}', [RegistrationReviewController::class, 'preview'])->whereIn('document', ['cor', 'formal_photo'])->name('registrations.documents.show');
    Route::get('/registrations/{registration}/documents/{document}/download', [RegistrationReviewController::class, 'download'])->whereIn('document', ['cor', 'formal_photo'])->name('registrations.documents.download');
    Route::patch('/registrations/{registration}/review', [RegistrationReviewController::class, 'update'])->name('registrations.review');
    Route::patch('/registrations/{registration}/archive', [RegistrationReviewController::class, 'archive'])->name('registrations.archive');
    Route::patch('/registrations/{registration}/restore', [RegistrationReviewController::class, 'restore'])->name('registrations.restore');
    Route::delete('/registrations/{registration}', [RegistrationReviewController::class, 'destroy'])->name('registrations.destroy');
    Route::get('/registrations/{registration}', [RegistrationReviewController::class, 'show'])->name('registrations.show');
    Route::post('/accounts/students/component', [NstpAdminAccountController::class, 'bulkAssignStudents'])->name('accounts.students.component.bulk');
    Route::patch('/accounts/{user}/component', [NstpAdminAccountController::class, 'updateComponent'])->name('accounts.component.update');
    Route::patch('/accounts/{user}/enrollments/{enrollment}/rotc-category', [NstpAdminAccountController::class, 'updateRotcCategory'])->name('accounts.rotc-category.update');
    Route::get('/accounts/{user}', [NstpAdminAccountController::class, 'show'])->name('accounts.show');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{type}/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/reports/{type}/document', [ReportController::class, 'document'])->name('reports.document');
    Route::get('/reports/{type}/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
    Route::get('/reports/{type}/print', [ReportController::class, 'print'])->name('reports.print');
    $chedApplicationRoutes();
    $communityProjectRoutes();
    $projectTaskRoutes();
    $evaluationRoutes();
    $facilitatorRequirementRoutes();
    $honorariumViewerRoutes();
    $honorariumRequestRoutes();
    $honorariumApprovalRoutes();
    $engagementAnalyticsRoutes();
    $documentConfigurationRoutes();
    $documentReviewRoutes();
    $reviewCategoryRoutes();
    $workflowRoutes();
    $notificationRuleRoutes();
    $policyRequirementRoutes();
    Route::resource('announcements', NstpAdminAnnouncementController::class)->except('show');
    Route::get('/components', [NstpAdminComponentController::class, 'index'])->name('components.index');
    Route::get('/components/export', [DirectoryExportController::class, 'components'])->name('components.export');
    Route::patch('/components/selection-availability', [NstpAdminComponentController::class, 'updateSelectionAvailability'])->name('components.selection-availability');
    Route::get('/components/{component}/edit', [NstpAdminComponentController::class, 'edit'])->name('components.edit');
    Route::put('/components/{component}', [NstpAdminComponentController::class, 'update'])->name('components.update');
    Route::put('/components/{component}/assessment-profile', [NstpAdminComponentController::class, 'updateAssessmentProfile'])->name('components.assessment-profile.update');
    Route::get('/sections', [NstpAdminSectioningController::class, 'index'])->name('sections.index');
    Route::get('/sections/export', [DirectoryExportController::class, 'sections'])->name('sections.export');
    Route::get('/sections/create', [NstpAdminSectionController::class, 'create'])->name('sections.create');
    Route::post('/sections', [NstpAdminSectionController::class, 'store'])->name('sections.store');
    Route::get('/sections/{section}/edit', [NstpAdminSectionController::class, 'edit'])->name('sections.edit');
    Route::put('/sections/{section}', [NstpAdminSectionController::class, 'update'])->name('sections.update');
    Route::get('/sectioning', [NstpAdminSectioningController::class, 'index'])->name('sectioning.index');
    Route::post('/sectioning/automate', [NstpAdminSectioningController::class, 'automate'])->name('sectioning.automate');
    $learningManagementRoutes();
    $scheduleRoutes();
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.store');
    Route::get('/register', [StudentRegistrationController::class, 'create'])->name('register');
    Route::post('/register', [StudentRegistrationController::class, 'store'])->middleware('throttle:5,1')->name('register.store');
});

Route::get('/locations/provinces/{provinceCode}/cities', [PhilippineLocationController::class, 'cities'])
    ->whereNumber('provinceCode')->middleware('throttle:60,1')->name('locations.cities');
Route::get('/locations/cities/{cityCode}/barangays', [PhilippineLocationController::class, 'barangays'])
    ->whereNumber('cityCode')->middleware('throttle:60,1')->name('locations.barangays');

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/notifications/announcements/{announcement}/open', [NotificationController::class, 'openAnnouncement'])->name('notifications.announcements.open');
    Route::get('/announcements/{announcement}/attachment', [PortalAnnouncementController::class, 'downloadAttachment'])->name('announcements.attachment.download');
    Route::get('/notifications/events/{notification}/open', [NotificationController::class, 'openEvent'])->name('notifications.events.open');
    Route::get('/notifications/categories/{category}/open', [NotificationController::class, 'openCategory'])
        ->whereIn('category', ['announcements', 'materials', 'assessments', 'attendance', 'messages'])
        ->name('notifications.categories.open');
    Route::get('/notifications/student/{notification}/open', [NotificationController::class, 'openStudent'])->name('notifications.student.open');
    Route::post('/notifications/{announcement}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('/ai-assistant/{conversation?}', [PortalAiAssistantController::class, 'index'])->name('ai-assistant.index');
    Route::post('/ai-assistant/{conversation?}', [PortalAiAssistantController::class, 'store'])->middleware('throttle:15,1')->name('ai-assistant.store');
    Route::delete('/ai-assistant/conversations/{conversation}', [PortalAiAssistantController::class, 'destroy'])->name('ai-assistant.destroy');
});

Route::get('/profile-photo', ProfilePhotoController::class)
    ->middleware('auth')
    ->name('profile.photo');

Route::prefix('student')->name('student.')->middleware(['auth', 'student'])->group(function () {
    Route::get('/required-documents', [StudentRequiredDocumentController::class, 'create'])->name('required-documents.create');
    Route::post('/required-documents', [StudentRequiredDocumentController::class, 'store'])->name('required-documents.store');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'super_admin'])->group(function () use ($learningManagementRoutes, $scheduleRoutes, $documentConfigurationRoutes, $documentReviewRoutes, $reviewCategoryRoutes, $workflowRoutes, $notificationRuleRoutes, $policyRequirementRoutes, $chedApplicationRoutes, $communityProjectRoutes, $projectTaskRoutes, $evaluationRoutes, $facilitatorRequirementRoutes, $engagementAnalyticsRoutes) {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::view('/system-guide', 'admin.system-guide')->name('system-guide');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/password', [ProfileController::class, 'updatePassword'])->name('password.update');
    Route::get('/messages/{contact?}', [PortalMessageController::class, 'index'])->name('messages.index');
    Route::post('/messages/{recipient}', [PortalMessageController::class, 'store'])->name('messages.store');
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create');
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
    Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    Route::get('/users/export', [DirectoryExportController::class, 'staff'])->name('users.export');
    Route::get('/students', [StudentAccountController::class, 'index'])->name('students.index');
    Route::get('/students/import', [StudentImportController::class, 'create'])->name('students.import.create');
    Route::post('/students/import', [StudentImportController::class, 'store'])->name('students.import.store');
    Route::get('/students/import/template', [StudentImportController::class, 'template'])->name('students.import.template');
    Route::get('/students/import/template/sql', [StudentImportController::class, 'sqlTemplate'])->name('students.import.sql-template');
    Route::post('/students/email-access', [StudentAccountController::class, 'bulkEmailAccess'])->middleware('throttle:3,1')->name('students.email-access');
    Route::get('/students/{student}/qr', [StudentAccountController::class, 'qr'])->name('students.qr');
    Route::get('/students/{student}/qr/download', [StudentAccountController::class, 'downloadQr'])->name('students.qr.download');
    Route::get('/students/export', [DirectoryExportController::class, 'students'])->name('students.export');
    Route::get('/registrations', [RegistrationReviewController::class, 'index'])->name('registrations.index');
    Route::patch('/registrations/settings', [SystemSettingController::class, 'updateRegistration'])->name('registrations.settings.update');
    Route::get('/registrations/{registration}/documents/{document}', [RegistrationReviewController::class, 'preview'])->whereIn('document', ['cor', 'formal_photo'])->name('registrations.documents.show');
    Route::get('/registrations/{registration}/documents/{document}/download', [RegistrationReviewController::class, 'download'])->whereIn('document', ['cor', 'formal_photo'])->name('registrations.documents.download');
    Route::patch('/registrations/{registration}/review', [RegistrationReviewController::class, 'update'])->name('registrations.review');
    Route::patch('/registrations/{registration}/archive', [RegistrationReviewController::class, 'archive'])->name('registrations.archive');
    Route::patch('/registrations/{registration}/restore', [RegistrationReviewController::class, 'restore'])->name('registrations.restore');
    Route::delete('/registrations/{registration}', [RegistrationReviewController::class, 'destroy'])->name('registrations.destroy');
    Route::get('/registrations/{registration}', [RegistrationReviewController::class, 'show'])->name('registrations.show');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/delete', [UserController::class, 'confirmDestroy'])->name('users.delete');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::patch('/users/{user}/status', [UserController::class, 'toggleStatus'])->name('users.status');
    Route::put('/users/{user}/password', [UserController::class, 'resetPassword'])->name('users.password');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{type}/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/reports/{type}/document', [ReportController::class, 'document'])->name('reports.document');
    Route::get('/reports/{type}/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
    Route::get('/reports/{type}/print', [ReportController::class, 'print'])->name('reports.print');
    $chedApplicationRoutes();
    $communityProjectRoutes();
    $projectTaskRoutes();
    $evaluationRoutes();
    $facilitatorRequirementRoutes();
    $engagementAnalyticsRoutes();
    $documentConfigurationRoutes();
    $documentReviewRoutes();
    $reviewCategoryRoutes();
    $workflowRoutes();
    $notificationRuleRoutes();
    $policyRequirementRoutes();
    Route::get('/database-backup', [DatabaseBackupController::class, 'index'])->name('database-backup.index');
    Route::post('/database-backup/download', [DatabaseBackupController::class, 'download'])->middleware('throttle:2,1')->name('database-backup.download');
    Route::post('/database-backup/archive', [DatabaseBackupController::class, 'archive'])->middleware('throttle:2,1')->name('database-backup.archive');
    Route::post('/database-backup/upload', [DatabaseBackupController::class, 'upload'])->middleware('throttle:2,1')->name('database-backup.upload');
    Route::get('/database-backup/archives/{archive}/download', [DatabaseBackupController::class, 'downloadArchive'])->name('database-backup.archives.download');
    Route::post('/database-backup/archives/{archive}/restore', [DatabaseBackupController::class, 'restore'])->middleware('throttle:2,1')->name('database-backup.archives.restore');
    Route::delete('/database-backup/archives/{archive}', [DatabaseBackupController::class, 'destroy'])->name('database-backup.archives.destroy');
    Route::get('/system-logs', [SystemLogController::class, 'index'])->name('system-logs.index');
    Route::get('/system-logs/export', [SystemLogController::class, 'export'])->name('system-logs.export');
    Route::resource('announcements', NstpAdminAnnouncementController::class)->except('show');
    Route::get('/archives', [ArchiveController::class, 'index'])->name('archives.index');
    Route::delete('/archives/bulk-delete', [ArchiveController::class, 'bulkDestroy'])->name('archives.bulk-destroy');
    Route::get('/archives/{type}/export', [ArchiveController::class, 'export'])->name('archives.export');
    Route::post('/archives/{type}', [ArchiveController::class, 'archiveAll'])->name('archives.archive');
    Route::patch('/archives/{type}/restore', [ArchiveController::class, 'restoreAll'])->name('archives.restore');
    Route::delete('/archives/{type}', [ArchiveController::class, 'destroyAll'])->name('archives.destroy');
    Route::get('/settings', [SystemSettingController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SystemSettingController::class, 'update'])->name('settings.update');
    Route::get('/components', [NstpAdminComponentController::class, 'index'])->name('components.index');
    Route::get('/components/export', [DirectoryExportController::class, 'components'])->name('components.export');
    Route::patch('/components/selection-availability', [NstpAdminComponentController::class, 'updateSelectionAvailability'])->name('components.selection-availability');
    Route::get('/components/{component}/edit', [NstpAdminComponentController::class, 'edit'])->name('components.edit');
    Route::put('/components/{component}', [NstpAdminComponentController::class, 'update'])->name('components.update');
    Route::put('/components/{component}/assessment-profile', [NstpAdminComponentController::class, 'updateAssessmentProfile'])->name('components.assessment-profile.update');
    Route::get('/sections', [NstpAdminSectioningController::class, 'index'])->name('sections.index');
    Route::get('/sections/export', [DirectoryExportController::class, 'sections'])->name('sections.export');
    Route::get('/sections/create', [NstpAdminSectionController::class, 'create'])->name('sections.create');
    Route::post('/sections', [NstpAdminSectionController::class, 'store'])->name('sections.store');
    Route::get('/sections/{section}/edit', [NstpAdminSectionController::class, 'edit'])->name('sections.edit');
    Route::put('/sections/{section}', [NstpAdminSectionController::class, 'update'])->name('sections.update');
    Route::get('/sectioning', [NstpAdminSectioningController::class, 'index'])->name('sectioning.index');
    Route::post('/sectioning/automate', [NstpAdminSectioningController::class, 'automate'])->name('sectioning.automate');
    $learningManagementRoutes();
    $scheduleRoutes();
});

Route::prefix('facilitator')->name('facilitator.')->middleware(['auth', 'facilitator'])->group(function () use ($learningManagementRoutes, $omrScannerRoutes, $communityProjectRoutes, $projectTaskRoutes, $evaluationRoutes, $facilitatorRequirementRoutes, $honorariumViewerRoutes, $engagementAnalyticsRoutes) {
    Route::get('/dashboard', FacilitatorDashboardController::class)->name('dashboard');
    Route::view('/system-guide', 'facilitator.system-guide')->name('system-guide');
    Route::get('/announcements', [PortalAnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('/messages/groups', [PortalMessageController::class, 'storeGroup'])->name('messages.groups.store');
    Route::get('/messages/groups/{group}', [PortalMessageController::class, 'group'])->name('messages.groups.show');
    Route::post('/messages/groups/{group}', [PortalMessageController::class, 'storeGroupMessage'])->name('messages.groups.messages.store');
    Route::get('/messages/{contact?}', [PortalMessageController::class, 'index'])->whereNumber('contact')->name('messages.index');
    Route::post('/messages/{recipient}', [PortalMessageController::class, 'store'])->name('messages.store');
    Route::get('/students', [FacilitatorStudentController::class, 'index'])->name('students.index');
    Route::get('/students/{student}', [FacilitatorStudentController::class, 'show'])->name('students.show');
    Route::get('/profile', [PortalProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [PortalProfileController::class, 'update'])->name('profile.update');
    Route::put('/password', [PortalProfileController::class, 'updatePassword'])->name('password.update');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{type}/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/reports/{type}/document', [ReportController::class, 'document'])->name('reports.document');
    Route::get('/reports/{type}/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
    Route::get('/reports/{type}/print', [ReportController::class, 'print'])->name('reports.print');
    $communityProjectRoutes();
    $projectTaskRoutes();
    $evaluationRoutes();
    $facilitatorRequirementRoutes();
    $honorariumViewerRoutes();
    $engagementAnalyticsRoutes();
    $learningManagementRoutes();
    $omrScannerRoutes();
});

Route::prefix('coordinator')->name('coordinator.')->middleware(['auth', 'coordinator'])->group(function () use ($omrScannerRoutes, $scheduleRoutes, $communityProjectRoutes, $projectTaskRoutes, $evaluationRoutes, $facilitatorRequirementRoutes, $honorariumViewerRoutes, $honorariumRequestRoutes, $engagementAnalyticsRoutes) {
    Route::get('/dashboard', CoordinatorDashboardController::class)->name('dashboard');
    Route::view('/system-guide', 'coordinator.system-guide')->name('system-guide');
    Route::get('/messages/{contact?}', [PortalMessageController::class, 'index'])->name('messages.index');
    Route::post('/messages/{recipient}', [PortalMessageController::class, 'store'])->name('messages.store');
    Route::resource('announcements', NstpAdminAnnouncementController::class)->except('show');
    Route::get('/components', [CoordinatorMonitoringController::class, 'components'])->name('components.index');
    Route::get('/components/export', [DirectoryExportController::class, 'components'])->name('components.export');
    Route::get('/accounts', [CoordinatorAccountController::class, 'index'])->name('accounts.index');
    Route::patch('/accounts/{user}/enrollments/{enrollment}/rotc-category', [CoordinatorAccountController::class, 'updateRotcCategory'])->name('accounts.rotc-category.update');
    Route::get('/accounts/{user}', [CoordinatorAccountController::class, 'show'])->name('accounts.show');
    Route::get('/rotc-approvals', [CoordinatorRotcApprovalController::class, 'index'])->name('rotc-approvals.index');
    Route::get('/rotc-approvals/{enrollment}/proof', [CoordinatorRotcApprovalController::class, 'showProof'])->name('rotc-approvals.proof');
    Route::get('/rotc-approvals/{enrollment}/proof/file', [CoordinatorRotcApprovalController::class, 'streamProof'])->name('rotc-approvals.proof.file');
    Route::get('/rotc-approvals/{enrollment}/proof/download', [CoordinatorRotcApprovalController::class, 'downloadProof'])->name('rotc-approvals.proof.download');
    Route::patch('/rotc-approvals/{enrollment}/approve', [CoordinatorRotcApprovalController::class, 'approve'])->name('rotc-approvals.approve');
    Route::get('/sections', [CoordinatorMonitoringController::class, 'sections'])->name('sections.index');
    Route::get('/sections/export', [DirectoryExportController::class, 'sections'])->name('sections.export');
    $scheduleRoutes();
    Route::get('/attendance', [CoordinatorMonitoringController::class, 'attendance'])->name('attendance.index');
    Route::get('/attendance/{attendance}', [ManagementAttendanceController::class, 'show'])->name('attendance.show');
    Route::patch('/attendance/{attendance}/scan-mode', [ManagementAttendanceController::class, 'updateScanMode'])->name('attendance.scan-mode');
    Route::post('/attendance/{attendance}/scan', [ManagementAttendanceController::class, 'scan'])->name('attendance.scan');
    Route::get('/materials', [MaterialController::class, 'index'])->name('materials.index');
    Route::get('/materials/create', [MaterialController::class, 'create'])->name('materials.create');
    Route::post('/materials', [MaterialController::class, 'store'])->name('materials.store');
    Route::get('/materials/{material}/download', [MaterialController::class, 'download'])->name('materials.download');
    Route::get('/performance', [CoordinatorMonitoringController::class, 'performance'])->name('performance.index');
    Route::get('/assessments', [AssessmentController::class, 'index'])->name('assessments.index');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{type}/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/reports/{type}/document', [ReportController::class, 'document'])->name('reports.document');
    Route::get('/reports/{type}/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
    Route::get('/reports/{type}/print', [ReportController::class, 'print'])->name('reports.print');
    Route::get('/serial-numbers', [CoordinatorSerialNumberController::class, 'index'])->name('serial-numbers.index');
    Route::post('/serial-numbers', [CoordinatorSerialNumberController::class, 'store'])->name('serial-numbers.store');
    Route::get('/serial-numbers/{serialNumberRelease}', [CoordinatorSerialNumberController::class, 'show'])->name('serial-numbers.show');
    Route::get('/serial-numbers/{serialNumberRelease}/source-file', [CoordinatorSerialNumberController::class, 'download'])->name('serial-numbers.download');
    Route::put('/serial-numbers/{serialNumberRelease}/students/{enrollment}', [CoordinatorSerialNumberController::class, 'storeSerial'])->name('serial-numbers.students.store');
    $communityProjectRoutes();
    $projectTaskRoutes();
    $evaluationRoutes();
    $facilitatorRequirementRoutes();
    $honorariumViewerRoutes();
    $honorariumRequestRoutes();
    $engagementAnalyticsRoutes();
    Route::get('/assessments/create', [AssessmentController::class, 'create'])->name('assessments.create');
    Route::post('/assessments', [AssessmentController::class, 'store'])->name('assessments.store');
    Route::get('/assessments/{assessment}', [AssessmentController::class, 'show'])->name('assessments.show');
    Route::get('/grades', [AssessmentController::class, 'grades'])->name('grades.index');
    Route::put('/grades/{section}/structure', [AssessmentController::class, 'updateGradeStructure'])->name('grades.structure');
    Route::delete('/grades/categories/{category}', [AssessmentController::class, 'destroyGradeCategory'])->name('grades.categories.destroy');
    Route::post('/grades/{section}/items', [AssessmentController::class, 'storeGradeItem'])->name('grades.items.store');
    Route::put('/grades/items/{assessment}', [AssessmentController::class, 'updateGradeItem'])->name('grades.items.update');
    Route::delete('/grades/items/{assessment}', [AssessmentController::class, 'destroyGradeItem'])->name('grades.items.destroy');
    Route::put('/grades/{section}/scores', [AssessmentController::class, 'updateGradeScore'])->name('grades.scores.update');
    Route::get('/profile', [PortalProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [PortalProfileController::class, 'update'])->name('profile.update');
    Route::put('/password', [PortalProfileController::class, 'updatePassword'])->name('password.update');
    $omrScannerRoutes();
});

Route::prefix('student')->name('student.')->middleware(['auth', 'student'])->group(function () use ($communityProjectRoutes, $projectTaskRoutes, $evaluationRoutes, $engagementAnalyticsRoutes) {
    Route::get('/dashboard', StudentDashboardController::class)->name('dashboard');
    Route::view('/system-guide', 'student.system-guide')->name('system-guide');
    Route::get('/announcements', [PortalAnnouncementController::class, 'index'])->name('announcements.index');
    Route::get('/messages/groups/{group}', [PortalMessageController::class, 'group'])->name('messages.groups.show');
    Route::post('/messages/groups/{group}', [PortalMessageController::class, 'storeGroupMessage'])->name('messages.groups.messages.store');
    Route::get('/messages/{contact?}', [PortalMessageController::class, 'index'])->whereNumber('contact')->name('messages.index');
    Route::post('/messages/{recipient}', [PortalMessageController::class, 'store'])->name('messages.store');
    Route::get('/profile', [StudentProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [PortalProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/details', [StudentProfileController::class, 'update'])->name('profile.details.update');
    Route::put('/password', [PortalProfileController::class, 'updatePassword'])->name('password.update');
    Route::get('/component', [StudentComponentController::class, 'edit'])->name('component.edit');
    Route::put('/component', [StudentComponentController::class, 'update'])->name('component.update');
    Route::get('/attendance', [StudentAttendanceController::class, 'index'])->name('attendance.index');
    Route::get('/attendance/qr', [StudentAttendanceController::class, 'qr'])->name('attendance.qr');
    Route::get('/student-id', [StudentAttendanceController::class, 'studentId'])->name('id-card');
    Route::get('/materials', [StudentLearningController::class, 'materials'])->name('materials.index');
    Route::get('/materials/{material}/download', [MaterialController::class, 'download'])->name('materials.download');
    Route::get('/learning-recommendations', [StudentLearningRecommendationController::class, 'index'])->name('recommendations.index');
    Route::post('/learning-recommendations', [StudentLearningRecommendationController::class, 'generate'])->middleware('throttle:5,1')->name('recommendations.generate');
    Route::get('/project-proposal-guide', [StudentProjectProposalGuideController::class, 'index'])->name('proposal-guide.index');
    Route::post('/project-proposal-guide', [StudentProjectProposalGuideController::class, 'generate'])->middleware('throttle:5,1')->name('proposal-guide.generate');
    $communityProjectRoutes();
    $projectTaskRoutes();
    $evaluationRoutes();
    $engagementAnalyticsRoutes();
    Route::get('/assessments', [StudentLearningController::class, 'assessments'])->name('assessments.index');
    Route::get('/assessments/{assessment}', [StudentLearningController::class, 'show'])->name('assessments.show');
    Route::post('/assessments/{assessment}/submit', [StudentLearningController::class, 'submit'])->name('assessments.submit');
    Route::get('/grades', [StudentLearningController::class, 'grades'])->name('grades.index');
    Route::get('/reports', StudentReportController::class)->name('reports.index');
    Route::get('/reports/download/{type}', [StudentReportController::class, 'download'])->name('reports.download');
    Route::get('/documents', [StudentDocumentController::class, 'index'])->name('documents.index');
    Route::post('/documents/{documentForm}', [StudentDocumentController::class, 'store'])->name('documents.store');
    Route::get('/documents/{documentForm}/template', [StudentDocumentController::class, 'downloadTemplate'])->name('documents.template');
    Route::get('/documents/submissions/{documentSubmission}', [StudentDocumentController::class, 'downloadSubmission'])->name('documents.submissions.download');
});
