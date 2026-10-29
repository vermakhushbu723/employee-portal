<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user()->load('employee.department', 'employee.shift', 'employee.manager');

        $announcements = Announcement::visibleTo($user)->latest('published_at')->limit(3)->get();

        return view('account.profile', [
            'user' => $user,
            'shift' => $user->currentShift(),
            'announcements' => $announcements,
            'emails' => $user->emails()->get(),
        ]);
    }

    public function updatePassword(Request $request, AuditLogger $audit)
    {
        $data = $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8), 'different:current_password'],
        ]);

        $request->user()->update(['password' => $data['password']]);
        $audit->log('auth.password_changed', 'Changed account password');

        return back()->with('success', 'Your password has been changed.');
    }
}
