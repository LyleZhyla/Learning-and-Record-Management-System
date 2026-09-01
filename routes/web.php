<?php

use App\Http\Controllers\Admin\ArchiveController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DatabaseBackupController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SystemLogController;
use App\Http\Controllers\Admin\SystemSettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PhilippineLocationController;
use App\Http\Controllers\Auth\StudentRegistrationController;
use App\Http\Controllers\Coordinator\AccountController as CoordinatorAccountController;
use App\Http\Controllers\Coordinator\DashboardController as CoordinatorDashboardController;
use App\Http\Controllers\Coordinator\MonitoringController as CoordinatorMonitoringController;
use App\Http\Controllers\Coordinator\RotcApprovalController as CoordinatorRotcApprovalController;
use App\Http\Controllers\Facilitator\DashboardController as FacilitatorDashboardController;
use App\Http\Controllers\Facilitator\StudentController as FacilitatorStudentController;
use App\Http\Controllers\Learning\AssessmentController;
use App\Http\Controllers\Learning\AttendanceController as ManagementAttendanceController;
use App\Http\Controllers\Learning\MaterialController;
use App\Http\Controllers\Learning\OmrScannerController;
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
use App\Http\Controllers\Student\AttendanceController as StudentAttendanceController;
use App\Http\Controllers\Student\ComponentController as StudentComponentController;
use App\Http\Controllers\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\Student\LearningController as StudentLearningController;
use App\Http\Controllers\Student\ProfileController as StudentProfileController;
use App\Http\Controllers\Student\ReportController as StudentReportController;
use App\Http\Controllers\StudentAccountController;
use App\Http\Controllers\StudentImportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    $routeName = auth()->user()->dashboardRouteName();

    abort_unless($routeName, 403, 'A dashboard is not yet available for this account role.');

    return redirect()->route($routeName);
});

$learningManagementRoutes = function (): void {
    Route::get('/attendance', [ManagementAttendanceController::class, 'index'])->name('attendance.index');
    Route::get('/attendance/create', [ManagementAttendanceController::class, 'create'])->name('attendance.create');
    Route::post('/attendance', [ManagementAttendanceController::class, 'store'])->name('attendance.store');
    Route::get('/attendance/{attendance}', [ManagementAttendanceController::class, 'show'])->name('attendance.show');
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
    Route::get('/assessments/{assessment}', [AssessmentController::class, 'show'])->name('assessments.show');
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
    Route::get('/answer-sheet-scanner/{sheet}/print', [OmrScannerController::class, 'printable'])->name('omr.print');
    Route::post('/answer-sheet-scanner/{sheet}/grade', [OmrScannerController::class, 'grade'])->name('omr.grade');
};

Route::prefix('nstp-admin')->name('nstp_admin.')->middleware(['auth', 'nstp_admin'])->group(function () use ($learningManagementRoutes) {
    Route::get('/dashboard', NstpAdminDashboardController::class)->name('dashboard');
    Route::get('/profile', [NstpAdminProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [NstpAdminProfileController::class, 'update'])->name('profile.update');
    Route::put('/password', [NstpAdminProfileController::class, 'updatePassword'])->name('password.update');
    Route::get('/accounts', [NstpAdminAccountController::class, 'index'])->name('accounts.index');
    Route::get('/students', [StudentAccountController::class, 'index'])->name('students.index');
    Route::get('/students/import', [StudentImportController::class, 'create'])->name('students.import.create');
    Route::post('/students/import', [StudentImportController::class, 'store'])->name('students.import.store');
    Route::get('/students/import/template', [StudentImportController::class, 'template'])->name('students.import.template');
    Route::get('/students/{student}/qr', [StudentAccountController::class, 'qr'])->name('students.qr');
    Route::get('/students/{student}/qr/download', [StudentAccountController::class, 'downloadQr'])->name('students.qr.download');
    Route::post('/accounts/students/component', [NstpAdminAccountController::class, 'bulkAssignStudents'])->name('accounts.students.component.bulk');
    Route::patch('/accounts/{user}/component', [NstpAdminAccountController::class, 'updateComponent'])->name('accounts.component.update');
    Route::patch('/accounts/{user}/enrollments/{enrollment}/rotc-category', [NstpAdminAccountController::class, 'updateRotcCategory'])->name('accounts.rotc-category.update');
    Route::get('/accounts/{user}', [NstpAdminAccountController::class, 'show'])->name('accounts.show');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{type}/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/reports/{type}/print', [ReportController::class, 'print'])->name('reports.print');
    Route::resource('announcements', NstpAdminAnnouncementController::class)->except('show');
    Route::get('/components', [NstpAdminComponentController::class, 'index'])->name('components.index');
    Route::get('/components/{component}/edit', [NstpAdminComponentController::class, 'edit'])->name('components.edit');
    Route::put('/components/{component}', [NstpAdminComponentController::class, 'update'])->name('components.update');
    Route::get('/sections', [NstpAdminSectioningController::class, 'index'])->name('sections.index');
    Route::get('/sections/create', [NstpAdminSectionController::class, 'create'])->name('sections.create');
    Route::post('/sections', [NstpAdminSectionController::class, 'store'])->name('sections.store');
    Route::get('/sections/{section}/edit', [NstpAdminSectionController::class, 'edit'])->name('sections.edit');
    Route::put('/sections/{section}', [NstpAdminSectionController::class, 'update'])->name('sections.update');
    Route::get('/sectioning', [NstpAdminSectioningController::class, 'index'])->name('sectioning.index');
    Route::post('/sectioning/enroll', [NstpAdminSectioningController::class, 'enroll'])->name('sectioning.enroll');
    Route::post('/sectioning/automate', [NstpAdminSectioningController::class, 'automate'])->name('sectioning.automate');
    Route::delete('/sectioning/enrollments/{enrollment}', [NstpAdminSectioningController::class, 'destroy'])->name('sectioning.destroy');
    $learningManagementRoutes();
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/register', [StudentRegistrationController::class, 'create'])->name('register');
    Route::post('/register', [StudentRegistrationController::class, 'store'])->middleware('throttle:5,1')->name('register.store');
    Route::get('/locations/provinces/{provinceCode}/cities', [PhilippineLocationController::class, 'cities'])->whereNumber('provinceCode')->middleware('throttle:60,1')->name('locations.cities');
    Route::get('/locations/cities/{cityCode}/barangays', [PhilippineLocationController::class, 'barangays'])->whereNumber('cityCode')->middleware('throttle:60,1')->name('locations.barangays');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/notifications/announcements/{announcement}/open', [NotificationController::class, 'openAnnouncement'])->name('notifications.announcements.open');
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

Route::prefix('admin')->name('admin.')->middleware(['auth', 'super_admin'])->group(function () use ($learningManagementRoutes) {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/password', [ProfileController::class, 'updatePassword'])->name('password.update');
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/students', [StudentAccountController::class, 'index'])->name('students.index');
    Route::get('/students/import', [StudentImportController::class, 'create'])->name('students.import.create');
    Route::post('/students/import', [StudentImportController::class, 'store'])->name('students.import.store');
    Route::get('/students/import/template', [StudentImportController::class, 'template'])->name('students.import.template');
    Route::get('/students/{student}/qr', [StudentAccountController::class, 'qr'])->name('students.qr');
    Route::get('/students/{student}/qr/download', [StudentAccountController::class, 'downloadQr'])->name('students.qr.download');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::patch('/users/{user}/status', [UserController::class, 'toggleStatus'])->name('users.status');
    Route::put('/users/{user}/password', [UserController::class, 'resetPassword'])->name('users.password');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{type}/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/reports/{type}/print', [ReportController::class, 'print'])->name('reports.print');
    Route::get('/database-backup', [DatabaseBackupController::class, 'index'])->name('database-backup.index');
    Route::post('/database-backup/download', [DatabaseBackupController::class, 'download'])->middleware('throttle:2,1')->name('database-backup.download');
    Route::get('/system-logs', [SystemLogController::class, 'index'])->name('system-logs.index');
    Route::get('/announcements', [NstpAdminAnnouncementController::class, 'index'])->name('announcements.index');
    Route::delete('/announcements/{announcement}', [NstpAdminAnnouncementController::class, 'destroy'])->name('announcements.destroy');
    Route::get('/archives', [ArchiveController::class, 'index'])->name('archives.index');
    Route::post('/archives/{type}', [ArchiveController::class, 'archiveAll'])->name('archives.archive');
    Route::patch('/archives/{type}/restore', [ArchiveController::class, 'restoreAll'])->name('archives.restore');
    Route::get('/settings', [SystemSettingController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SystemSettingController::class, 'update'])->name('settings.update');
    Route::get('/components', [NstpAdminComponentController::class, 'index'])->name('components.index');
    Route::get('/components/{component}/edit', [NstpAdminComponentController::class, 'edit'])->name('components.edit');
    Route::put('/components/{component}', [NstpAdminComponentController::class, 'update'])->name('components.update');
    Route::get('/sections', [NstpAdminSectioningController::class, 'index'])->name('sections.index');
    Route::get('/sections/create', [NstpAdminSectionController::class, 'create'])->name('sections.create');
    Route::post('/sections', [NstpAdminSectionController::class, 'store'])->name('sections.store');
    Route::get('/sections/{section}/edit', [NstpAdminSectionController::class, 'edit'])->name('sections.edit');
    Route::put('/sections/{section}', [NstpAdminSectionController::class, 'update'])->name('sections.update');
    Route::get('/sectioning', [NstpAdminSectioningController::class, 'index'])->name('sectioning.index');
    Route::post('/sectioning/enroll', [NstpAdminSectioningController::class, 'enroll'])->name('sectioning.enroll');
    Route::post('/sectioning/automate', [NstpAdminSectioningController::class, 'automate'])->name('sectioning.automate');
    Route::delete('/sectioning/enrollments/{enrollment}', [NstpAdminSectioningController::class, 'destroy'])->name('sectioning.destroy');
    $learningManagementRoutes();
});

Route::prefix('facilitator')->name('facilitator.')->middleware(['auth', 'facilitator'])->group(function () use ($learningManagementRoutes, $omrScannerRoutes) {
    Route::get('/dashboard', FacilitatorDashboardController::class)->name('dashboard');
    Route::get('/announcements', [PortalAnnouncementController::class, 'index'])->name('announcements.index');
    Route::get('/messages/{contact?}', [PortalMessageController::class, 'index'])->name('messages.index');
    Route::post('/messages/{recipient}', [PortalMessageController::class, 'store'])->name('messages.store');
    Route::get('/students', [FacilitatorStudentController::class, 'index'])->name('students.index');
    Route::get('/students/{student}', [FacilitatorStudentController::class, 'show'])->name('students.show');
    Route::get('/profile', [PortalProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [PortalProfileController::class, 'update'])->name('profile.update');
    Route::put('/password', [PortalProfileController::class, 'updatePassword'])->name('password.update');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{type}/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/reports/{type}/print', [ReportController::class, 'print'])->name('reports.print');
    $learningManagementRoutes();
    $omrScannerRoutes();
});

Route::prefix('coordinator')->name('coordinator.')->middleware(['auth', 'coordinator'])->group(function () use ($omrScannerRoutes) {
    Route::get('/dashboard', CoordinatorDashboardController::class)->name('dashboard');
    Route::resource('announcements', NstpAdminAnnouncementController::class)->except('show');
    Route::get('/components', [CoordinatorMonitoringController::class, 'components'])->name('components.index');
    Route::get('/accounts', [CoordinatorAccountController::class, 'index'])->name('accounts.index');
    Route::patch('/accounts/{user}/enrollments/{enrollment}/rotc-category', [CoordinatorAccountController::class, 'updateRotcCategory'])->name('accounts.rotc-category.update');
    Route::get('/accounts/{user}', [CoordinatorAccountController::class, 'show'])->name('accounts.show');
    Route::get('/rotc-approvals', [CoordinatorRotcApprovalController::class, 'index'])->name('rotc-approvals.index');
    Route::get('/rotc-approvals/{enrollment}/proof', [CoordinatorRotcApprovalController::class, 'showProof'])->name('rotc-approvals.proof');
    Route::get('/rotc-approvals/{enrollment}/proof/file', [CoordinatorRotcApprovalController::class, 'streamProof'])->name('rotc-approvals.proof.file');
    Route::get('/rotc-approvals/{enrollment}/proof/download', [CoordinatorRotcApprovalController::class, 'downloadProof'])->name('rotc-approvals.proof.download');
    Route::patch('/rotc-approvals/{enrollment}/approve', [CoordinatorRotcApprovalController::class, 'approve'])->name('rotc-approvals.approve');
    Route::get('/sections', [CoordinatorMonitoringController::class, 'sections'])->name('sections.index');
    Route::get('/attendance', [CoordinatorMonitoringController::class, 'attendance'])->name('attendance.index');
    Route::get('/attendance/{attendance}', [ManagementAttendanceController::class, 'show'])->name('attendance.show');
    Route::post('/attendance/{attendance}/scan', [ManagementAttendanceController::class, 'scan'])->name('attendance.scan');
    Route::get('/performance', [CoordinatorMonitoringController::class, 'performance'])->name('performance.index');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{type}/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/reports/{type}/print', [ReportController::class, 'print'])->name('reports.print');
    Route::get('/assessments/create', [AssessmentController::class, 'create'])->name('assessments.create');
    Route::post('/assessments', [AssessmentController::class, 'store'])->name('assessments.store');
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

Route::prefix('student')->name('student.')->middleware(['auth', 'student'])->group(function () {
    Route::get('/dashboard', StudentDashboardController::class)->name('dashboard');
    Route::get('/announcements', [PortalAnnouncementController::class, 'index'])->name('announcements.index');
    Route::get('/messages/{contact?}', [PortalMessageController::class, 'index'])->name('messages.index');
    Route::post('/messages/{recipient}', [PortalMessageController::class, 'store'])->name('messages.store');
    Route::get('/profile', [StudentProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [PortalProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/details', [StudentProfileController::class, 'update'])->name('profile.details.update');
    Route::put('/password', [PortalProfileController::class, 'updatePassword'])->name('password.update');
    Route::get('/component', [StudentComponentController::class, 'edit'])->name('component.edit');
    Route::put('/component', [StudentComponentController::class, 'update'])->name('component.update');
    Route::get('/attendance', [StudentAttendanceController::class, 'index'])->name('attendance.index');
    Route::get('/attendance/qr', [StudentAttendanceController::class, 'qr'])->name('attendance.qr');
    Route::get('/materials', [StudentLearningController::class, 'materials'])->name('materials.index');
    Route::get('/materials/{material}/download', [MaterialController::class, 'download'])->name('materials.download');
    Route::get('/assessments', [StudentLearningController::class, 'assessments'])->name('assessments.index');
    Route::get('/assessments/{assessment}', [StudentLearningController::class, 'show'])->name('assessments.show');
    Route::post('/assessments/{assessment}/submit', [StudentLearningController::class, 'submit'])->name('assessments.submit');
    Route::get('/grades', [StudentLearningController::class, 'grades'])->name('grades.index');
    Route::get('/reports', StudentReportController::class)->name('reports.index');
});
