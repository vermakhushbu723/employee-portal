<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DailyWorkReportController;
use App\Http\Controllers\Employee;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LeadReviewController;
use App\Http\Controllers\LiveController;
use App\Http\Controllers\LogoutRequestController;
use App\Http\Controllers\Manager;
use App\Http\Controllers\MeetingAdminController;
use App\Http\Controllers\MeetingRoomController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\WhatsAppController;
use App\Http\Controllers\WorkCorrectionController;
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

// Public "Install the app" page — share this link with employees.
Route::view('install', 'install')->name('install');

// Built-in meeting room — customer joins with the secret link (no login)
Route::controller(MeetingRoomController::class)->prefix('meet/{token}')->where(['token' => '[A-Za-z0-9]{32}'])->name('meet.guest')->group(function () {
    Route::get('/', 'guest')->name('');
    Route::post('join', 'join')->middleware('throttle:20,1')->name('.join');
    Route::post('signal', 'signal')->middleware('throttle:600,1')->name('.signal');
    Route::get('poll', 'poll')->middleware('throttle:200,1')->name('.poll');
    Route::post('leave', 'leave')->name('.leave');
});

Route::middleware('auth')->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    // Shared by every role
    Route::get('live/pulse', [LiveController::class, 'pulse'])->name('live.pulse');
    Route::get('notifications', [AnnouncementController::class, 'index'])->name('notifications.index');
    Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Voice note / e-mail file of a lead (the employee, or admin / their manager)
    Route::get('lead-files/{comm}', [Employee\LeadCommunicationController::class, 'file'])->whereNumber('comm')->name('lead-files.show');

    // WhatsApp inside the portal (each user's own linked WhatsApp)
    Route::controller(WhatsAppController::class)->prefix('wa')->name('wa.')->group(function () {
        Route::post('start', 'start')->name('start');
        Route::get('status', 'status')->name('status');
        Route::get('chats', 'chats')->name('chats');
        Route::get('messages', 'messages')->name('messages');
        Route::post('send', 'send')->middleware('throttle:60,1')->name('send');
        Route::post('logout', 'logout')->name('logout');
        Route::post('pair', 'pair')->middleware('throttle:6,1')->name('pair');
    });

    // Built-in meeting room API for staff (employee, admin, manager) — joins inside the dashboard
    Route::controller(MeetingRoomController::class)->prefix('room/{meeting}')->whereNumber('meeting')->name('meet.')->group(function () {
        Route::post('join', 'join')->name('join');
        Route::post('signal', 'signal')->name('signal');
        Route::get('poll', 'poll')->name('poll');
        Route::post('leave', 'leave')->name('leave');
    });
});

/*
|--------------------------------------------------------------------------
| Employee portal  —  /employee/*
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:employee,team_leader', 'punched-in'])
    ->prefix('employee')->name('employee.')
    ->group(function () {
        Route::get('dashboard', Employee\DashboardController::class)->name('dashboard');

        // My assigned work + task details
        Route::controller(Employee\TaskController::class)->prefix('tasks')->name('tasks.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('{assignment}', 'show')->whereNumber('assignment')->name('show');
            Route::post('{assignment}/start', 'start')->whereNumber('assignment')->name('start');
            Route::post('{assignment}/progress', 'progress')->whereNumber('assignment')->name('progress');
            Route::post('{assignment}/submit', 'submit')->whereNumber('assignment')->name('submit');
        });

        // Leave history, apply leave, leave charges
        Route::get('leave-history', [Employee\LeaveController::class, 'index'])->name('leaves.index');
        Route::post('leaves', [Employee\LeaveController::class, 'store'])->name('leaves.store');
        Route::get('leave-charges', [Employee\LeaveController::class, 'charges'])->name('leaves.charges');

        Route::get('performance', Employee\PerformanceController::class)->name('performance');
    });

// Employee sections that admin can also give a manager / team leader (users.features)
Route::middleware(['auth', 'feature:attendance'])->prefix('employee')->name('employee.')->group(function () {
        // Active-time heartbeat (navbar timer)
        Route::post('activity/heartbeat', Employee\ActivityController::class)->name('activity.heartbeat');

        // Check-in / Attendance + My Attendance calendar
        Route::controller(Employee\AttendanceController::class)->prefix('attendance')->name('attendance.')->group(function () {
            Route::get('/', 'today')->name('today');
            Route::get('history', 'history')->name('history');
            Route::post('check-in', 'checkIn')->name('check-in');
            Route::post('late-request', 'lateRequest')->name('late-request');
            Route::post('check-out', 'checkOut')->name('check-out');
            Route::post('exception-checkout', 'exceptionCheckout')->name('exception-checkout');
            Route::post('logout-decision/seen', 'decisionSeen')->name('decision-seen');
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

});

Route::middleware(['auth', 'feature:leads', 'punched-in'])->prefix('employee')->name('employee.')->group(function () {
        // Leads
        Route::controller(Employee\LeadController::class)->prefix('leads')->name('leads.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('{lead}', 'show')->whereNumber('lead')->name('show');
            Route::patch('{lead}/status', 'updateStatus')->whereNumber('lead')->name('status');
        });

        // Calls (talk time), WhatsApp, e-mail, voice notes, inline purpose / remark, details panel
        Route::controller(Employee\LeadCommunicationController::class)->prefix('leads/{lead}')->whereNumber('lead')->name('leads.')->group(function () {
            Route::get('panel', 'panel')->name('panel');
            Route::get('templates', 'templates')->name('templates');
            Route::patch('inline', 'inline')->name('inline');
            Route::post('call', 'startCall')->name('call.start');
            Route::post('call/{call}', 'endCall')->whereNumber('call')->name('call.end');
            Route::post('whatsapp', 'whatsapp')->middleware('throttle:30,1')->name('whatsapp');
            Route::post('email', 'email')->middleware('throttle:20,1')->name('email');
            Route::post('voice-note', 'voiceNote')->name('voice-note');
        });

});

Route::middleware(['auth', 'feature:meetings', 'punched-in'])->prefix('employee')->name('employee.')->group(function () {
        // Customer meetings: book, accept / decline, join (In Meeting), end
        Route::controller(Employee\MeetingController::class)->prefix('meetings')->name('meetings.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::patch('{meeting}', 'update')->whereNumber('meeting')->name('update');
            Route::post('{meeting}/accept', 'accept')->whereNumber('meeting')->name('accept');
            Route::post('{meeting}/decline', 'decline')->whereNumber('meeting')->name('decline');
            Route::post('{meeting}/join', 'join')->whereNumber('meeting')->name('join');
            Route::get('{meeting}/room', 'room')->whereNumber('meeting')->name('room');
        });
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

        // Live tracking, meetings and work-data corrections (same screens for admin and manager)
        Route::get('tracking', TrackingController::class)->name('tracking');
        Route::get('meetings', [MeetingAdminController::class, 'index'])->name('meetings.index');
        Route::post('meetings', [MeetingAdminController::class, 'store'])->name('meetings.store');
        Route::post('meetings/{meeting}/cancel', [MeetingAdminController::class, 'cancel'])->whereNumber('meeting')->name('meetings.cancel');
        Route::get('meetings/{meeting}/room', [MeetingAdminController::class, 'room'])->whereNumber('meeting')->name('meetings.room');
        Route::post('meetings/{meeting}/review', [MeetingAdminController::class, 'review'])->whereNumber('meeting')->name('meetings.review');
        Route::get('calls', [\App\Http\Controllers\CallLogController::class, 'index'])->name('calls.index');
        Route::get('logout-requests', [LogoutRequestController::class, 'index'])->name('logout-requests.index');
        Route::get('review', [LeadReviewController::class, 'index'])->name('review.index');
        Route::patch('review/leads/{lead}', [LeadReviewController::class, 'update'])->whereNumber('lead')->name('review.update');
        Route::post('logout-requests/{session}/approve', [LogoutRequestController::class, 'approve'])->whereNumber('session')->name('logout-requests.approve');
        Route::post('logout-requests/{session}/reject', [LogoutRequestController::class, 'reject'])->whereNumber('session')->name('logout-requests.reject');
        Route::controller(WorkCorrectionController::class)->prefix('corrections')->name('corrections.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('new/{session}', 'create')->whereNumber('session')->name('create');
            Route::post('new/{session}', 'store')->whereNumber('session')->name('store');
            Route::get('{correction}', 'show')->whereNumber('correction')->name('show');
            Route::post('{correction}/approve', 'approve')->whereNumber('correction')->name('approve');
            Route::post('{correction}/reject', 'reject')->whereNumber('correction')->name('reject');
        });

        Route::get('reports/daily', [DailyWorkReportController::class, 'index'])->name('reports.daily');   // own team, view only
        Route::get('targets', [Manager\TargetController::class, 'index'])->name('targets');
        Route::post('targets', [Manager\TargetController::class, 'update'])->name('targets.update');

        Route::post('lead-task', [Admin\DataController::class, 'leadTask'])->name('lead-task');   // own team only

        Route::get('assign-work', [Manager\AssignWorkController::class, 'create'])->name('assign-work.create');
        Route::post('assign-work', [Manager\AssignWorkController::class, 'store'])->name('assign-work.store');

        Route::controller(Manager\ApprovalController::class)->prefix('approvals')->name('approvals.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('leaves/{leave}/approve', 'approveLeave')->whereNumber('leave')->name('leaves.approve');
            Route::post('leaves/{leave}/reject', 'rejectLeave')->whereNumber('leave')->name('leaves.reject');
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
        Route::get('mail-check', [Admin\MailCheckController::class, 'index'])->name('mail-check');
        Route::post('mail-check', [Admin\MailCheckController::class, 'send'])->middleware('throttle:5,1')->name('mail-check.send');

        // Live tracking, meetings and work-data corrections (same screens for admin and manager)
        Route::get('tracking', TrackingController::class)->name('tracking');
        Route::get('meetings', [MeetingAdminController::class, 'index'])->name('meetings.index');
        Route::post('meetings', [MeetingAdminController::class, 'store'])->name('meetings.store');
        Route::post('meetings/{meeting}/cancel', [MeetingAdminController::class, 'cancel'])->whereNumber('meeting')->name('meetings.cancel');
        Route::get('meetings/{meeting}/room', [MeetingAdminController::class, 'room'])->whereNumber('meeting')->name('meetings.room');
        Route::post('meetings/{meeting}/review', [MeetingAdminController::class, 'review'])->whereNumber('meeting')->name('meetings.review');
        Route::get('calls', [\App\Http\Controllers\CallLogController::class, 'index'])->name('calls.index');
        Route::get('logout-requests', [LogoutRequestController::class, 'index'])->name('logout-requests.index');
        Route::get('review', [LeadReviewController::class, 'index'])->name('review.index');
        Route::patch('review/leads/{lead}', [LeadReviewController::class, 'update'])->whereNumber('lead')->name('review.update');
        Route::post('logout-requests/{session}/approve', [LogoutRequestController::class, 'approve'])->whereNumber('session')->name('logout-requests.approve');
        Route::post('logout-requests/{session}/reject', [LogoutRequestController::class, 'reject'])->whereNumber('session')->name('logout-requests.reject');
        Route::controller(WorkCorrectionController::class)->prefix('corrections')->name('corrections.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('new/{session}', 'create')->whereNumber('session')->name('create');
            Route::post('new/{session}', 'store')->whereNumber('session')->name('store');
            Route::get('{correction}', 'show')->whereNumber('correction')->name('show');
            Route::post('{correction}/approve', 'approve')->whereNumber('correction')->name('approve');
            Route::post('{correction}/reject', 'reject')->whereNumber('correction')->name('reject');
        });

        Route::get('employees', [Admin\EmployeeController::class, 'index'])->name('employees.index');
        Route::get('employees/create', [Admin\EmployeeController::class, 'create'])->name('employees.create');
        Route::post('employees', [Admin\EmployeeController::class, 'store'])->name('employees.store');
        Route::get('employees/{user}/edit', [Admin\EmployeeController::class, 'edit'])->whereNumber('user')->name('employees.edit');
        Route::put('employees/{user}', [Admin\EmployeeController::class, 'update'])->whereNumber('user')->name('employees.update');
        Route::patch('employees/{user}/status', [Admin\EmployeeController::class, 'toggleStatus'])->whereNumber('user')->name('employees.status');

        Route::get('interested-leads', Admin\InterestedLeadController::class)->name('leads.interested');

        // Department-wise data tracking
        Route::get('roles', [Admin\RoleController::class, 'index'])->name('roles.index');
        Route::post('roles', [Admin\RoleController::class, 'store'])->name('roles.store');
        Route::delete('roles/{role}', [Admin\RoleController::class, 'destroy'])->whereNumber('role')->name('roles.destroy');

        Route::get('departments', [Admin\DepartmentDataController::class, 'index'])->name('departments.index');
        Route::post('departments', [Admin\DepartmentDataController::class, 'store'])->name('departments.store');
        Route::get('departments/{department}', [Admin\DepartmentDataController::class, 'show'])->whereNumber('department')->name('departments.show');

        // Daily work report (admin: all employees, download, correct, punch out, mail)
        Route::get('reports/daily', [DailyWorkReportController::class, 'index'])->name('reports.daily');
        Route::post('reports/{session}/punch-out', [DailyWorkReportController::class, 'punchOut'])->whereNumber('session')->name('reports.punch-out');
        Route::post('reports/{session}/send', [DailyWorkReportController::class, 'send'])->whereNumber('session')->name('reports.send');

        // Data upload & share (calling lists)
        Route::controller(Admin\DataController::class)->prefix('data')->name('data.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('upload', 'upload')->name('upload');
            Route::get('template', 'template')->name('template');
            Route::post('share', 'share')->name('share');
            Route::post('lead-task', 'leadTask')->name('lead-task');
            Route::post('recall/{user}', 'recall')->whereNumber('user')->name('recall');
            Route::post('shares/{share}/rollback', 'rollback')->whereNumber('share')->name('rollback');
            Route::get('batches/{batch}', 'show')->whereNumber('batch')->name('show');
        });

        Route::get('late-requests', [Admin\LateCheckinController::class, 'index'])->name('late-requests.index');
        Route::post('late-requests/{lateRequest}/approve', [Admin\LateCheckinController::class, 'approve'])->whereNumber('lateRequest')->name('late-requests.approve');
        Route::post('late-requests/{lateRequest}/reject', [Admin\LateCheckinController::class, 'reject'])->whereNumber('lateRequest')->name('late-requests.reject');

        Route::get('leaves', [Admin\LeaveController::class, 'index'])->name('leaves.index');
        Route::post('leaves/{leave}/approve', [Admin\LeaveController::class, 'approve'])->whereNumber('leave')->name('leaves.approve');
        Route::post('leaves/{leave}/reject', [Admin\LeaveController::class, 'reject'])->whereNumber('leave')->name('leaves.reject');
    });
