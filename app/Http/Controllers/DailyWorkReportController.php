<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\AttendanceSession;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\DailyReportService;
use App\Services\WorkScoreService;
use App\Support\Duration;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Daily work report.
 *   Manager: own team only (corrections go through Correction Requests for review).
 *   Admin/HR: everyone, can download, punch an employee out and (re)send the mail.
 */
class DailyWorkReportController extends Controller
{
    public function __construct(private DailyReportService $reports) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $isAdmin = $user->role->portal() === 'admin';
        $date = $this->date($request);

        $q = mb_strtolower(trim((string) $request->query('q')));
        $dept = $request->integer('department') ?: null;
        $status = (string) $request->query('status');

        $users = $this->visibleUsers($user)
            ->when($dept, fn ($c) => $c->filter(fn ($u) => $u->employee?->department_id === $dept)->values())
            ->when($q !== '', fn ($c) => $c->filter(fn ($u) => str_contains(mb_strtolower($u->name.' '.$u->employee_code.' '.($u->employee?->manager?->name ?? '')), $q))->values());
        $all = $this->reports->rows($users, $date);

        if ($request->boolean('export')) {
            abort_unless($isAdmin, 403);

            return $this->export($all, $date);
        }

        $state = fn ($r) => self::state($r);
        $rows = $status ? $all->filter(fn ($r) => $state($r) === $status)->values() : $all;

        $perPage = 10;
        $page = max(1, $request->integer('page', 1));
        $paged = new \Illuminate\Pagination\LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page, ['path' => $request->url(), 'query' => $request->query()]);

        $total = max(1, $all->count());
        $count = fn ($s) => $all->filter(fn ($r) => $state($r) === $s)->count();
        $kpis = [
            ['people-fill', 'Total Employees', $all->count(), null, 'primary'],
            ['broadcast', 'Working Now (Live)', $count('working'), null, 'success'],
            ['telephone-fill', 'Busy (On Call/Meeting)', $count('busy'), null, 'warning'],
            ['hourglass-split', 'Idle', $count('idle'), null, 'warning'],
            ['box-arrow-in-right', 'Punch In', $all->filter(fn ($r) => $r['session'])->count(), null, 'success'],
            ['box-arrow-right', 'Punch Out', $all->filter(fn ($r) => $r['session']?->check_out_at)->count(), null, 'danger'],
            ['clock-history', 'Extended Shift', $all->filter(fn ($r) => ($r['window']['extension'] ?? 0) > 0)->count(), null, 'info'],
            ['geo-alt-fill', 'Location Off', $all->filter(fn ($r) => $r['user']->location_status === 'denied')->count(), null, 'secondary'],
            ['battery-half', 'Low Battery', $all->filter(fn ($r) => $r['user']->battery_level !== null && $r['user']->battery_level <= 20 && $r['user']->device_at?->isToday())->count(), null, 'danger'],
        ];
        foreach ($kpis as &$k) {
            $k[3] = round($k[2] / $total * 100).'%';
        }
        unset($k);
        $kpis[0][3] = null;

        return view('reports.daily', [
            'rows' => $paged,
            'date' => $date,
            'isAdmin' => $isAdmin,
            'kpis' => $kpis,
            'departments' => \App\Models\Department::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'filters' => ['q' => $request->query('q'), 'department' => $dept, 'status' => $status],
            'mailReady' => \App\Support\MailPool::ready(),
            'portal' => $user->role->portal(),
        ]);
    }

    /** Live / day state used for the status filter and tiles. */
    public static function state(array $r): string
    {
        if (! $r['session']) {
            return 'not_logged_in';
        }
        if ($r['session']->check_out_at) {
            return 'punched_out';
        }

        return match ($r['live']['state'] ?? 'working') {
            'meeting' => 'busy',
            'idle', 'break' => 'idle',
            'offline' => 'offline',
            default => 'working',
        };
    }

    /** Admin punches out an employee who forgot / left (the day is scored from their active time). */
    public function punchOut(Request $request, AttendanceSession $session, AttendanceService $attendance)
    {
        abort_unless($session->isOpen(), 422, 'Already punched out.');

        $attendance->forceCheckOut($session, $request->user());

        return back()->with('success', "{$session->user->name} punched out by admin. Day scored from active time.");
    }

    public function send(AttendanceSession $session)
    {
        $mail = $this->reports->send($session->load('user.employee.manager', 'breaks'));

        return back()->with($mail === 'sent' ? 'success' : 'error', 'Report: '.trim($this->mailMessage($mail)));
    }

    private function visibleUsers(User $user)
    {
        if ($user->role->portal() === 'admin') {
            // Employees, team leaders, and managers whose punch in / out admin enabled
            return User::whereIn('role', [Role::Employee->value, Role::TeamLeader->value, Role::Manager->value])->where('status', 'active')
                ->with('employee.manager', 'employee.department')->orderBy('name')->get()
                ->filter(fn ($u) => $u->role !== Role::Manager || $u->tracksAttendance())->values();
        }

        return $user->teamMembers()->where('status', 'active')->with('employee.manager', 'employee.department')->orderBy('name')->get();
    }

    private function date(Request $request): Carbon
    {
        $value = (string) $request->query('date');

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && Carbon::parse($value)->lte(today())
            ? Carbon::parse($value)->startOfDay()
            : today();
    }

    private function mailMessage(string $result): string
    {
        return match ($result) {
            'sent' => ' Report e-mailed.',
            'not_configured' => ' (E-mail is not set up yet, so the report was not mailed.)',
            'no_recipients' => ' (No e-mail address on file, report not mailed.)',
            'failed' => ' (Report e-mail failed — check mail settings.)',
            default => '',
        };
    }

    private function export($rows, Carbon $date)
    {
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Employee', 'ID', 'Department', 'Manager', 'Punch In', 'Punch Out', 'Shift (actual)', 'Extended till', 'Status', 'Active', 'Inactive', 'Break',
                'Calls Done', 'Calls Target', 'Leads Created', 'Interested', 'Matured', 'Immatured', 'Meetings Done', 'Meetings', 'Emails Sent', 'WhatsApp Sent',
                'Progress %', 'Battery %', 'Location', 'Work Score %', 'Attendance Weekly %', 'Attendance Monthly %', 'Score Avg Weekly %', 'Score Avg Monthly %', 'Attendance', 'Work Status', 'Admin Note'], ',', '"', '');
            foreach ($rows as $r) {
                $s = $r['session'];
                $w = $r['work'];
                $u = $r['user'];
                [, $label] = WorkScoreService::battery($w['grade'] ?? 'none');
                fputcsv($out, [
                    $u->name, $u->employee_code, $u->employee?->department?->name, $u->employee?->manager?->name,
                    $s?->check_in_at->format('h:i A'), $s?->check_out_at?->format('h:i A'),
                    $r['shift']->label(), $r['window'] ? $r['window']['extended_end']->format('h:i A') : '',
                    str_replace('_', ' ', self::state($r)),
                    $w ? Duration::hms($w['active']) : '', $w ? Duration::hms($w['idle']) : '', $w ? Duration::hms($w['break']) : '',
                    $r['calls'], $r['calls_target'], $w['created'] ?? 0, $w['interested'] ?? 0, $r['matured'], $r['immatured'],
                    $r['meetings_done'], $r['meetings_total'], $r['emails'], $r['whatsapp'], $r['progress'],
                    $u->battery_level, $u->location_status === 'denied' ? 'Off' : ($u->latitude ? 'On' : ''),
                    $w['score'] ?? 0, $r['avg']['week_att'] ?? '', $r['avg']['month_att'] ?? '', $r['avg']['week_score'] ?? '', $r['avg']['month_score'] ?? '', $s ? $label : 'Absent', str_replace('_', ' ', $r['work_status']), $s?->correction_note,
                ], ',', '"', '');
            }
            fclose($out);
        }, 'daily-work-report-'.$date->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
