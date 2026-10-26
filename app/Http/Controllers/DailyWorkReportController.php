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

        $q = trim((string) $request->query('q'));
        $users = $this->visibleUsers($user)->when($q !== '', fn ($c) => $c->filter(
            fn ($u) => str_contains(strtolower($u->name.' '.$u->employee_code), strtolower($q)))->values());
        $rows = $this->reports->rows($users, $date);

        if ($isAdmin && $request->boolean('export')) {
            return $this->export($rows, $date);
        }

        return view('reports.daily', [
            'rows' => $rows,
            'date' => $date,
            'isAdmin' => $isAdmin,
            'summary' => [
                'full' => $rows->where('work.grade', 'full')->count(),
                'half' => $rows->where('work.grade', 'half')->count(),
                'quarter' => $rows->where('work.grade', 'quarter')->count(),
                'absent' => $rows->filter(fn ($r) => ! $r['session'] || $r['work']['grade'] === 'none')->count(),
            ],
            'mailReady' => ! in_array(config('mail.default'), ['log', 'array'], true),
        ]);
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
            return User::whereIn('role', [Role::Employee->value, Role::TeamLeader->value])->with('employee.manager')->orderBy('name')->get();
        }

        return $user->teamMembers()->with('employee.manager')->orderBy('name')->get();
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
            fputcsv($out, ['Employee', 'ID', 'Manager', 'Punch In', 'Punch Out', 'Active', 'Idle', 'Break', 'Leads', 'Lead Target', 'Interested', 'Interested Target', 'Tasks Done', 'Score %', 'Attendance', 'Admin Note'], ',', '"', '');
            foreach ($rows as $r) {
                $s = $r['session'];
                $w = $r['work'];
                [, $label] = WorkScoreService::battery($w['grade'] ?? 'none');
                fputcsv($out, [
                    $r['user']->name, $r['user']->employee_code, $r['user']->employee?->manager?->name,
                    $s?->check_in_at->format('h:i A'), $s?->check_out_at?->format('h:i A'),
                    $w ? Duration::hms($w['active']) : '', $w ? Duration::hms($w['idle']) : '', $w ? Duration::hms($w['break']) : '',
                    $w['created'] ?? 0, $w['targets']['leads'] ?? '', $w['interested'] ?? 0, $w['targets']['interested'] ?? '',
                    $r['tasks_done'].'/'.$r['tasks_total'], $w['score'] ?? 0, $s ? $label : 'Absent', $s?->correction_note,
                ], ',', '"', '');
            }
            fclose($out);
        }, 'daily-work-report-'.$date->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
