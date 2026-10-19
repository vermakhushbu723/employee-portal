<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $announcements = Announcement::visibleTo($request->user())
            ->latest('published_at')
            ->paginate(15);

        return view('account.notifications', compact('announcements'));
    }
}
