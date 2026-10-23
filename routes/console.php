<?php

use App\Models\AttendanceSession;
use App\Services\DailyReportService;
use App\Services\WorkScoreService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('portal:about', function () {
    $this->info('Employee Management & Work Tracking Portal');
})->purpose('Show portal information');

/*
| Close attendance days that were never punched out (browser closed / device off):
| the day ends at the last activity heartbeat, gets its work score + grade, and the
| daily report is mailed. Runs nightly; the calendar and reports also do it on demand.
*/
Artisan::command('attendance:finalize', function (WorkScoreService $score, DailyReportService $reports) {
    $open = AttendanceSession::whereNull('grade')->whereDate('work_date', '<', today())->pluck('id');
    $closed = $score->finalizePastDays();

    AttendanceSession::with('user.employee.manager', 'breaks')->whereIn('id', $open)->whereNull('report_sent_at')->get()
        ->each(fn ($session) => $reports->send($session));

    $this->info("Finalized {$closed} attendance day(s).");
})->purpose('Close past attendance days that were never punched out');

Schedule::command('attendance:finalize')->dailyAt('00:10');
