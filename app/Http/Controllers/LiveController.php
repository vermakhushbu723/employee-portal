<?php

namespace App\Http\Controllers;

use App\Enums\LeaveStatus;
use App\Models\Announcement;
use App\Models\LateCheckinRequest;
use App\Models\Lead;
use App\Models\LeadBatch;
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
            $versions['leads'] = md5((clone $mine)->count().'|'.(clone $mine)->max('updated_at'));
        }

        if ($user->role->portal() === 'admin') {
            $uploaded = Lead::whereNotNull('batch_id');
            $versions['data'] = md5((clone $uploaded)->count().'|'.(clone $uploaded)->max('updated_at').'|'.LeadBatch::max('id'));
            $versions['late'] = md5(LateCheckinRequest::where('status', LeaveStatus::Pending)->count().'|'.LateCheckinRequest::max('updated_at'));
        }

        return response()->json([
            'alerts' => (clone $visible)->where('published_at', '>=', now()->subDays(7))->count(),
            'latest_id' => (int) (clone $visible)->max('id'),
            'new' => (clone $visible)->where('id', '>', $after)->orderBy('id')->limit(5)->get(['id', 'type', 'title', 'body']),
            'versions' => $versions,
        ])->header('Cache-Control', 'no-store');
    }
}
