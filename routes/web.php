<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Employee;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Manager;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
| Website sign-out is separate from attendance checkout: signing out never
| closes an open attendance session.
*/

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
    Route::view('forgot-password', 'auth.forgot-password')->name('password.request');
});

Route::middleware('auth')->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    // Shared by every role
    Route::get('notifications', [AnnouncementController::class, 'index'])->name('notifications.index');
    Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
});

/*
|--------------------------------------------------------------------------
| Employee portal  —  /employee/*
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:employee,team_leader'])
    ->prefix('employee')->name('employee.')
    ->group(function () {
        Route::get('dashboard', Employee\DashboardController::class)->name('dashboard');

        // Check-in / Attendance + My Attendance calendar
        Route::controller(Employee\AttendanceController::class)->prefix('attendance')->name('attendance.')->group(function () {
            Route::get('/', 'today')->name('today');
            Route::get('history', 'history')->name('history');
            Route::post('check-in', 'checkIn')->name('check-in');
            Route::post('late-request', 'lateRequest')->name('late-request');
            Route::post('check-out', 'checkOut')->name('check-out');
            Route::post('exception-checkout', 'exceptionCheckout')->name('exception-checkout');
        });

        // Live work timer (+ JSON for the ticking UI)
        Route::get('work-timer', [Employee\WorkTimerController::class, 'index'])->name('work-timer');
        Route::get('work-timer/status', [Employee\WorkTimerController::class, 'status'])->name('work-timer.status');

        // Break management
        Route::controller(Employee\BreakController::class)->prefix('breaks')->name('breaks.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('start', 'start')->name('start');
            Route::post('end', 'end')->name('end');
        });

        // My assigned work + task details
        Route::controller(Employee\TaskController::class)->prefix('tasks')->name('tasks.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('{assignment}', 'show')->whereNumber('assignment')->name('show');
            Route::post('{assignment}/start', 'start')->whereNumber('assignment')->name('start');
            Route::post('{assignment}/progress', 'progress')->whereNumber('assignment')->name('progress');
            Route::post('{assignment}/submit', 'submit')->whereNumber('assignment')->name('submit');
        });

        // Leads
        Route::controller(Employee\LeadController::class)->prefix('leads')->name('leads.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('{lead}', 'show')->whereNumber('lead')->name('show');
            Route::patch('{lead}/status', 'updateStatus')->whereNumber('lead')->name('status');
        });

        // Leave history, apply leave, leave charges
        Route::get('leave-history', [Employee\LeaveController::class, 'index'])->name('leaves.index');
        Route::post('leaves', [Employee\LeaveController::class, 'store'])->name('leaves.store');
        Route::get('leave-charges', [Employee\LeaveController::class, 'charges'])->name('leaves.charges');

        Route::get('performance', Employee\PerformanceController::class)->name('performance');
    });

/*
|--------------------------------------------------------------------------
| Manager / Team Leader portal  —  /manager/*
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:manager,team_leader'])
    ->prefix('manager')->name('manager.')
    ->group(function () {
        Route::get('dashboard', Manager\DashboardController::class)->name('dashboard');
        Route::get('team', Manager\TeamController::class)->name('team');

        Route::get('assign-work', [Manager\AssignWorkController::class, 'create'])->name('assign-work.create');
        Route::post('assign-work', [Manager\AssignWorkController::class, 'store'])->name('assign-work.store');

        Route::controller(Manager\ApprovalController::class)->prefix('approvals')->name('approvals.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('leaves/{leave}/approve', 'approveLeave')->whereNumber('leave')->name('leaves.approve');
            Route::post('leaves/{leave}/reject', 'rejectLeave')->whereNumber('leave')->name('leaves.reject');
            Route::post('checkouts/{session}/review', 'reviewCheckout')->whereNumber('session')->name('checkouts.review');
        });
    });

/*
|--------------------------------------------------------------------------
| Admin / HR portal  —  /admin/*
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:super_admin,hr'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::get('dashboard', Admin\DashboardController::class)->name('dashboard');

        Route::get('employees', [Admin\EmployeeController::class, 'index'])->name('employees.index');
        Route::get('employees/create', [Admin\EmployeeController::class, 'create'])->name('employees.create');
        Route::post('employees', [Admin\EmployeeController::class, 'store'])->name('employees.store');
        Route::get('employees/{user}/edit', [Admin\EmployeeController::class, 'edit'])->whereNumber('user')->name('employees.edit');
        Route::put('employees/{user}', [Admin\EmployeeController::class, 'update'])->whereNumber('user')->name('employees.update');
        Route::patch('employees/{user}/status', [Admin\EmployeeController::class, 'toggleStatus'])->whereNumber('user')->name('employees.status');

        Route::get('late-requests', [Admin\LateCheckinController::class, 'index'])->name('late-requests.index');
        Route::post('late-requests/{lateRequest}/approve', [Admin\LateCheckinController::class, 'approve'])->whereNumber('lateRequest')->name('late-requests.approve');
        Route::post('late-requests/{lateRequest}/reject', [Admin\LateCheckinController::class, 'reject'])->whereNumber('lateRequest')->name('late-requests.reject');

        Route::get('leaves', [Admin\LeaveController::class, 'index'])->name('leaves.index');
        Route::post('leaves/{leave}/approve', [Admin\LeaveController::class, 'approve'])->whereNumber('leave')->name('leaves.approve');
        Route::post('leaves/{leave}/reject', [Admin\LeaveController::class, 'reject'])->whereNumber('leave')->name('leaves.reject');
    });
