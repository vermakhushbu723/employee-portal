<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\LeadCommunication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Admin / manager: Call Log — every call made from the portal with its talk time, note and the
 * attached call recording (play in the browser). Admin sees everyone, a manager / TL their team.
 */
class CallLogController extends Controller
{
    public function index(Request $request)
    {
        $me = $request->user();
        $portal = $me->role->portal();
        $staff = ($portal === 'admin'
            ? User::whereIn('role', [Role::Employee->value, Role::TeamLeader->value, Role::Manager->value])
            : $me->teamMembers())->orderBy('name')->get(['id', 'name', 'employee_code']);

        $from = $this->date($request->query('from')) ?? today();
        $to = $this->date($request->query('to')) ?? $from->copy();
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }
        $userId = $request->integer('user') ?: null;

        $base = fn () => LeadCommunication::where('channel', 'call')
            ->whereIn('user_id', $userId && $staff->contains('id', $userId) ? [$userId] : $staff->pluck('id'))
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);

        $summary = $base()->selectRaw('user_id, count(*) as calls, sum(case when duration_seconds > 0 then 1 else 0 end) as connected,
                coalesce(sum(duration_seconds), 0) as talk, sum(case when file_path is not null then 1 else 0 end) as recordings')
            ->groupBy('user_id')->get()->map(fn ($r) => $r->setRelation('user', $staff->firstWhere('id', $r->user_id)))
            ->sortByDesc('talk')->values();

        $calls = $base()->when($request->boolean('recorded'), fn ($q) => $q->whereNotNull('file_path'))
            ->with('user:id,name,employee_code', 'lead:id,name,mobile,city')->latest()->paginate(30)->withQueryString();

        return view('calls.index', [
            'portal' => $portal, 'staff' => $staff, 'summary' => $summary, 'calls' => $calls,
            'from' => $from, 'to' => $to, 'userId' => $userId,
            'totals' => ['calls' => $summary->sum('calls'), 'connected' => $summary->sum('connected'), 'talk' => $summary->sum('talk'), 'recordings' => $summary->sum('recordings')],
        ]);
    }

    private function date(?string $value): ?Carbon
    {
        try {
            return $value ? Carbon::parse($value)->startOfDay() : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
