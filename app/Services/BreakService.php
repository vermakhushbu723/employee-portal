<?php

namespace App\Services;

use App\Models\BreakSession;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** Start Break pauses the work timer; End Break resumes it. */
class BreakService
{
    public function __construct(
        private AttendanceService $attendance,
        private AuditLogger $audit,
    ) {}

    public function start(User $user): BreakSession
    {
        $session = $this->attendance->todaySession($user);

        if (! $session || ! $session->isOpen()) {
            throw ValidationException::withMessages(['action' => 'Check in first to start a break.']);
        }

        if ($session->activeBreak()) {
            throw ValidationException::withMessages(['action' => 'You are already on a break.']);
        }

        $break = $session->breaks()->create(['user_id' => $user->id, 'started_at' => now()]);

        $this->audit->log('break.start', 'Break started at '.$break->started_at->format('h:i A'), ['break_id' => $break->id], $user);

        return $break;
    }

    public function end(User $user): BreakSession
    {
        $break = $this->attendance->todaySession($user)?->activeBreak();

        if (! $break) {
            throw ValidationException::withMessages(['action' => 'You are not on a break.']);
        }

        $now = now();
        $break->update([
            'ended_at' => $now,
            'duration_minutes' => intdiv(max(0, $now->getTimestamp() - $break->started_at->getTimestamp()), 60),
        ]);

        $this->audit->log('break.end', "Break ended ({$break->duration_minutes} min)", ['break_id' => $break->id], $user);

        return $break;
    }
}
