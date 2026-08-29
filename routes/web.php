<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SystemLogController;
use App\Http\Controllers\Admin\SystemSettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Coordinator\DashboardController as CoordinatorDashboardController;
use App\Http\Controllers\Coordinator\MonitoringController as CoordinatorMonitoringController;
use App\Http\Controllers\Facilitator\DashboardController as FacilitatorDashboardController;
use App\Http\Controllers\Learning\AssessmentController;
use App\Http\Controllers\Learning\AttendanceController as ManagementAttendanceController;
use App\Http\Controllers\Learning\MaterialController;
use App\Http\Controllers\Learning\OmrScannerController;
use App\Http\Controllers\NstpAdmin\ComponentController as NstpAdminComponentController;
use App\Http\Controllers\NstpAdmin\DashboardController as NstpAdminDashboardController;
use App\Http\Controllers\NstpAdmin\ProfileController as NstpAdminProfileController;
use App\Http\Controllers\NstpAdmin\SectionController as NstpAdminSectionController;
use App\Http\Controllers\NstpAdmin\SectioningController as NstpAdminSectioningController;
use App\Http\Controllers\Portal\ProfileController as PortalProfileController;
use App\Http\Controllers\ProfilePhotoController;
use App\Http\Controllers\Student\AttendanceController as StudentAttendanceController;
use App\Http\Controllers\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\Student\LearningController as StudentLearningController;
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
    Route::get('/components', [NstpAdminComponentController::class, 'index'])->name('components.index');
    Route::get('/components/{component}/edit', [NstpAdminComponentController::class, 'edit'])->name('components.edit');
    Route::put('/components/{component}', [NstpAdminComponentController::class, 'update'])->name('components.update');
    Route::get('/sections', [NstpAdminSectionController::class, 'index'])->name('sections.index');
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
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/profile-photo', ProfilePhotoController::class)
    ->middleware('auth')
    ->name('profile.photo');

Route::prefix('admin')->name('admin.')->middleware(['auth', 'super_admin'])->group(function () use ($learningManagementRoutes) {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/password', [ProfileController::class, 'updatePassword'])->name('password.update');
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::patch('/users/{user}/status', [UserController::class, 'toggleStatus'])->name('users.status');
    Route::put('/users/{user}/password', [UserController::class, 'resetPassword'])->name('users.password');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{type}/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/reports/{type}/print', [ReportController::class, 'print'])->name('reports.print');
    Route::get('/system-logs', [SystemLogController::class, 'index'])->name('system-logs.index');
    Route::get('/settings', [SystemSettingController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SystemSettingController::class, 'update'])->name('settings.update');
    Route::get('/components', [NstpAdminComponentController::class, 'index'])->name('components.index');
    Route::get('/components/{component}/edit', [NstpAdminComponentController::class, 'edit'])->name('components.edit');
    Route::put('/components/{component}', [NstpAdminComponentController::class, 'update'])->name('components.update');
    Route::get('/sections', [NstpAdminSectionController::class, 'index'])->name('sections.index');
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
    Route::get('/profile', [PortalProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [PortalProfileController::class, 'update'])->name('profile.update');
    Route::put('/password', [PortalProfileController::class, 'updatePassword'])->name('password.update');
    $learningManagementRoutes();
    $omrScannerRoutes();
});

Route::prefix('coordinator')->name('coordinator.')->middleware(['auth', 'coordinator'])->group(function () use ($omrScannerRoutes) {
    Route::get('/dashboard', CoordinatorDashboardController::class)->name('dashboard');
    Route::get('/components', [CoordinatorMonitoringController::class, 'components'])->name('components.index');
    Route::get('/sections', [CoordinatorMonitoringController::class, 'sections'])->name('sections.index');
    Route::get('/attendance', [CoordinatorMonitoringController::class, 'attendance'])->name('attendance.index');
    Route::get('/attendance/{attendance}', [ManagementAttendanceController::class, 'show'])->name('attendance.show');
    Route::post('/attendance/{attendance}/scan', [ManagementAttendanceController::class, 'scan'])->name('attendance.scan');
    Route::get('/performance', [CoordinatorMonitoringController::class, 'performance'])->name('performance.index');
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
    Route::get('/profile', [PortalProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [PortalProfileController::class, 'update'])->name('profile.update');
    Route::put('/password', [PortalProfileController::class, 'updatePassword'])->name('password.update');
    Route::get('/attendance', [StudentAttendanceController::class, 'index'])->name('attendance.index');
    Route::get('/attendance/qr', [StudentAttendanceController::class, 'qr'])->name('attendance.qr');
    Route::get('/materials', [StudentLearningController::class, 'materials'])->name('materials.index');
    Route::get('/materials/{material}/download', [MaterialController::class, 'download'])->name('materials.download');
    Route::get('/assessments', [StudentLearningController::class, 'assessments'])->name('assessments.index');
    Route::get('/assessments/{assessment}', [StudentLearningController::class, 'show'])->name('assessments.show');
    Route::post('/assessments/{assessment}/submit', [StudentLearningController::class, 'submit'])->name('assessments.submit');
    Route::get('/grades', [StudentLearningController::class, 'grades'])->name('grades.index');
});
