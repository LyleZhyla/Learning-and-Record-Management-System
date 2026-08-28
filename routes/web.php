<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\NstpAdmin\DashboardController as NstpAdminDashboardController;
use App\Http\Controllers\NstpAdmin\ProfileController as NstpAdminProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    $routeName = auth()->user()->dashboardRouteName();

    abort_unless($routeName, 403, 'Hindi pa available ang dashboard para sa account role na ito.');

    return redirect()->route($routeName);
});

Route::prefix('nstp-admin')->name('nstp_admin.')->middleware(['auth', 'nstp_admin'])->group(function () {
    Route::get('/dashboard', NstpAdminDashboardController::class)->name('dashboard');
    Route::get('/profile', [NstpAdminProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [NstpAdminProfileController::class, 'update'])->name('profile.update');
    Route::put('/password', [NstpAdminProfileController::class, 'updatePassword'])->name('password.update');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::prefix('admin')->name('admin.')->middleware(['auth', 'super_admin'])->group(function () {
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
});
