<?php

namespace App\Services;

use App\Enums\Role;
use App\Mail\DailyWorkReport;
use App\Enums\LeadStatus;
use App\Models\AttendanceSession;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadCommunication;
use App\Models\Meeting;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Support\MailPool;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

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
        app(AttendanceService::class)->closeDueSessions();   // auto punch-out at the extended shift end

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

        // Day numbers per employee (one query each, not per row)
        $ids = $users->pluck('id');
        $calls = LeadActivity::whereIn('user_id', $ids)->whereDate('created_at', $date)
            ->whereIn('type', ['status_change', 'follow_up'])
            ->selectRaw('user_id, count(distinct lead_id) as n')->groupBy('user_id')->pluck('n', 'user_id');
        $matured = LeadActivity::whereIn('user_id', $ids)->whereDate('created_at', $date)->where('to_status', LeadStatus::Converted->value)
            ->whereNotIn('lead_id', Lead::whereNotNull('corrected_at')->whereNotIn('status', LeadStatus::positive())->select('id'))
            ->selectRaw('user_id, count(distinct lead_id) as n')->groupBy('user_id')->pluck('n', 'user_id');
        $comms = LeadCommunication::whereIn('user_id', $ids)->whereDate('created_at', $date)->where('status', '!=', 'failed')
            ->selectRaw('user_id, channel, count(*) as n')->groupBy('user_id', 'channel')->get()->groupBy('user_id');
        $meetings = Meeting::whereIn('user_id', $ids)->whereDate('scheduled_at', $date)->whereNotIn('status', ['cancelled', 'declined'])
            ->get(['user_id', 'status', 'outcome'])->groupBy('user_id');
        $callTargets = \App\Models\Target::whereIn('user_id', $ids)->where('period', 'daily')->where('metric', 'calls')->pluck('target_value', 'user_id');
        $live = $date->isToday() ? app(LiveStatusService::class)->rows($users)->keyBy(fn ($r) => $r['user']->id) : collect();
        $attendance = app(AttendanceService::class);
        // Weekly / monthly attendance % and work score average (shown next to the day's work score)
        $averages = app(TeamPerformanceService::class)->attendanceAverages($ids->all());

        return $users->map(function (User $user) use ($sessions, $tasks, $calls, $matured, $comms, $meetings, $callTargets, $live, $attendance, $averages) {
            $session = $sessions->get($user->id);
            $userTasks = $tasks->get($user->id, collect());
            $work = $session ? $this->score->forSession($user, $session) : null;
            $done = (int) ($session?->calls_made ?? $calls[$user->id] ?? 0);
            $target = (int) ($callTargets[$user->id] ?? config('portal.work_score.default_calls_target', 100));
            $m = $meetings->get($user->id, collect());
            $c = $comms->get($user->id, collect())->pluck('n', 'channel');
            $window = $session ? $attendance->shiftWindow($user, $session) : null;
            $mat = (int) ($matured[$user->id] ?? 0);
            $int = (int) ($work['interested'] ?? 0);

            return [
                'user' => $user,
                'session' => $session,
                'avg' => $averages[$user->id] ?? null,
                'work' => $work,
                'tasks_done' => $userTasks->filter(fn ($a) => $a->status->isDone())->count(),
                'tasks_total' => $userTasks->count(),
                'live' => $live->get($user->id),
                'shift' => $user->currentShift(),
                'window' => $window,
                'calls' => $done,
                'calls_target' => $target,
                'calls_pending' => max(0, $target - $done),
                'matured' => $mat,
                'immatured' => max(0, $int - $mat),
                'meetings_done' => $m->where('status', 'completed')->count(),
                'meetings_total' => $m->count(),
                'emails' => (int) ($c['email'] ?? 0),
                'whatsapp' => (int) ($c['whatsapp'] ?? 0),
                'progress' => $target ? (int) min(100, round($done / $target * 100)) : 0,
                'work_status' => ! $session ? 'not_started' : (($work['score'] ?? 0) >= 100 ? 'completed' : 'incomplete'),
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

        if (! MailPool::ready()) {
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

        $mail = new DailyWorkReport($session, $this->score->forSession($user, $session));

        // Rotates across the alert mailboxes; on an error the next one is tried.
        if (! MailPool::send($recipients->all(), $mail, "Daily report mail for session {$session->id}")) {
            return 'failed';
        }
        $session->forceFill(['report_sent_at' => now()])->saveQuietly();

        return 'sent';
    }
}
