<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Services\AttendanceCalendarService;
use App\Services\AttendanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceService $attendance) {}

    /** Screen 3 — today's check-in / checkout. */
    public function today(Request $request)
    {
        $user = $request->user();
        $session = $this->attendance->todaySession($user);
        $timer = $this->attendance->timer($user, $session);

        return view('employee.attendance.today', [
            'session' => $session,
            'timer' => $timer,
            'shift' => $user->currentShift(),
            'timeline' => $this->timeline($session),
            'eligibility' => $this->attendance->checkoutEligibility($user, $session, $timer),
        ]);
    }

    public function checkIn(Request $request)
    {
        $session = $this->attendance->checkIn($request->user(), $request->ip());

        return back()->with('success', 'Checked in at '.$session->check_in_at->format('h:i A').'. Your work timer has started.');
    }

    public function checkOut(Request $request)
    {
        $this->attendance->checkOut($request->user());

        return back()->with('success', 'Checked out successfully. Today\'s report has been saved.');
    }

    /** Rule 7 — shift end / illness / emergency checkout, reviewed by the manager. */
    public function exceptionCheckout(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:shift_end,illness,emergency'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $this->attendance->checkOut($request->user(), true, '['.str_replace('_', ' ', $data['type']).'] '.$data['reason']);

        return back()->with('success', 'Exception checkout recorded and sent to your manager for review.');
    }

    /** Screen 10 — monthly attendance calendar. */
    public function history(Request $request, AttendanceCalendarService $calendar)
    {
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month'))
            ? Carbon::createFromFormat('Y-m-d', $request->query('month').'-01')->startOfDay()
            : now()->startOfMonth();

        if ($month->gt(now())) {
            $month = now()->startOfMonth();
        }

        $data = $calendar->build($request->user(), $month);

        $recent = collect($data['days'])
            ->filter(fn ($d) => $d['status'] !== null && $d['date']->lte(today()))
            ->reverse()
            ->take(10);

        return view('employee.attendance.history', [...$data, 'recent' => $recent]);
    }

    private function timeline($session): array
    {
        if (! $session) {
            return [];
        }

        $items = [['time' => $session->check_in_at, 'label' => 'Checked In', 'tone' => 'success']];

        foreach ($session->breaks as $break) {
            $items[] = ['time' => $break->started_at, 'label' => 'Break Start', 'tone' => 'warning'];

            if ($break->ended_at) {
                $items[] = ['time' => $break->ended_at, 'label' => "Break End ({$break->duration_minutes}m)", 'tone' => 'success'];
            }
        }

        $items[] = $session->check_out_at
            ? ['time' => $session->check_out_at, 'label' => 'Checked Out', 'tone' => 'primary']
            : ['time' => null, 'label' => 'Yet to Check Out', 'tone' => 'secondary'];

        return $items;
    }
}
