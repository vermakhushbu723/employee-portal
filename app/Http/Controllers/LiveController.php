<?php

namespace App\Http\Controllers;

use App\Enums\LeaveStatus;
use App\Models\Announcement;
use App\Models\LateCheckinRequest;
use App\Models\Lead;
use App\Models\LeadBatch;
use App\Services\AttendanceService;
use Illuminate\Http\Request;

/**
 * Real-time "pulse", polled every few seconds by public/js/app.js.
 * Returns new notifications plus a short version string per live area
 * (my leads, shared data, late requests). When a version changes, the
 * browser re-fetches just that part of the page — no full refresh.
 */
class LiveController extends Controller
{
    public function pulse(Request $request)
    {
        $user = $request->user();
        $after = $request->integer('after');
        $visible = Announcement::visibleTo($user);

        $versions = [];

        if ($user->role->portal() === 'employee') {
            $mine = Lead::ownedBy($user);
            $versions['leads'] = md5((clone $mine)->count().'|'.(clone $mine)->max('updated_at').'|'
                .(clone $mine)->selectRaw('status, count(*) c')->groupBy('status')->toBase()->get()->map(fn ($r) => $r->status.$r->c)->implode(','));

            // Punch-in popup: changes when the window opens/closes or admin decides a late request
            $attendance = app(AttendanceService::class);
            $versions['punch'] = $attendance->mustPunchIn($user)
                ? md5(json_encode([$attendance->checkInState($user)['state'], $attendance->lateRequestToday($user)?->status?->value]))
                : 'in';
        }

        if ($user->role->portal() === 'admin') {
            $uploaded = Lead::whereNotNull('batch_id');
            // Status counts are part of the version so a change in the same second is still noticed
            $versions['data'] = md5((clone $uploaded)->count().'|'.(clone $uploaded)->max('updated_at').'|'.LeadBatch::max('id').'|'
                .(clone $uploaded)->whereNotNull('assigned_to')->count().'|'.(clone $uploaded)->where('status', '!=', 'new')->count());
            $versions['allleads'] = md5(Lead::count().'|'.Lead::max('updated_at').'|'.Lead::where('status', 'interested')->count());
            $versions['late'] = md5(LateCheckinRequest::where('status', LeaveStatus::Pending)->count().'|'.LateCheckinRequest::max('updated_at'));
        }

        // Live status dashboards (admin / manager): refresh about every 30 s, or at once when a meeting or correction changes
        if (in_array($user->role->portal(), ['admin', 'manager'], true)) {
            $versions['status'] = md5(intdiv(time(), 30).'|'.\App\Models\Meeting::max('updated_at').'|'.\App\Models\WorkCorrection::max('updated_at'));
        }

        return response()->json([
            'alerts' => (clone $visible)->where('published_at', '>=', now()->subDays(7))->count(),
            'latest_id' => (int) (clone $visible)->max('id'),
            'new' => (clone $visible)->where('id', '>', $after)->orderBy('id')->limit(5)->get(['id', 'type', 'title', 'body']),
            'versions' => $versions,
        ])->header('Cache-Control', 'no-store');
    }
}
