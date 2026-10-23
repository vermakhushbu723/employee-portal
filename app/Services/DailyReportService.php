<?php

namespace App\Services;

use App\Enums\Role;
use App\Mail\DailyWorkReport;
use App\Models\AttendanceSession;
use App\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Daily work report per employee: shown to their manager and admin,
 * e-mailed at punch-out and again after an admin correction.
 */
class DailyReportService
{
    public function __construct(private WorkScoreService $score) {}

    /** Report rows for a date. $users = who may be seen (manager: own team, admin: everyone). */
    public function rows(Collection $users, Carbon $date): Collection
    {
        // Close any earlier day left open (browser closed / device off)
        if ($date->lt(today())) {
            $this->score->finalizePastDays();
        }

        $sessions = AttendanceSession::with('breaks', 'user')
            ->whereIn('user_id', $users->pluck('id'))
            ->whereDate('work_date', $date)
            ->get()
            ->keyBy('user_id');

        $tasks = TaskAssignment::with('task')
            ->whereIn('user_id', $users->pluck('id'))
            ->whereDate('assigned_date', $date)
            ->get()
            ->groupBy('user_id');

        return $users->map(function (User $user) use ($sessions, $tasks) {
            $session = $sessions->get($user->id);
            $userTasks = $tasks->get($user->id, collect());

            return [
                'user' => $user,
                'session' => $session,
                'work' => $session ? $this->score->forSession($user, $session) : null,
                'tasks_done' => $userTasks->filter(fn ($a) => $a->status->isDone())->count(),
                'tasks_total' => $userTasks->count(),
            ];
        })->values();
    }

    /**
     * E-mail the day's report to the employee, their manager and (optionally) admins.
     * Returns 'sent', 'not_configured' or 'failed'. Never throws, so punch-out never breaks.
     */
    public function send(AttendanceSession $session): string
    {
        if (! config('portal.report_mail.enabled')) {
            return 'disabled';
        }

        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            Log::info("Daily report for session {$session->id} not mailed: mail is not configured (MAIL_MAILER=".config('mail.default').').');

            return 'not_configured';
        }

        $user = $session->user;
        $recipients = collect([$user->email, $user->employee?->manager?->email]);
        if (config('portal.report_mail.cc_admins')) {
            $recipients = $recipients->merge(User::whereIn('role', [Role::SuperAdmin->value, Role::Hr->value])->where('status', 'active')->pluck('email'));
        }
        $recipients = $recipients->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))->unique()->values();

        if ($recipients->isEmpty()) {
            return 'no_recipients';
        }

        try {
            Mail::to($recipients->all())->send(new DailyWorkReport($session, $this->score->forSession($user, $session)));
            $session->forceFill(['report_sent_at' => now()])->saveQuietly();

            return 'sent';
        } catch (Throwable $e) {
            Log::error("Daily report mail failed for session {$session->id}: ".$e->getMessage());

            return 'failed';
        }
    }
}
