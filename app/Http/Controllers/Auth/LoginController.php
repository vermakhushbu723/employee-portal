<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, AuditLogger $audit)
    {
        $credentials = [
            'employee_code' => strtoupper(trim($request->input('employee_code'))),
            'password' => $request->input('password'),
        ];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['employee_code' => 'Invalid Employee ID or password.']);
        }

        $user = Auth::user();

        if (! $user->isActive()) {
            Auth::logout();
            throw ValidationException::withMessages(['employee_code' => 'Your account is inactive. Please contact HR.']);
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        $audit->log('auth.login', 'Signed in to the portal', [], $user);

        return redirect()->intended(route($user->homeRoute()));
    }

    /**
     * Employees cannot sign out while punched in — they must punch out first
     * (which needs the punch-out rules met), or admin can punch them out.
     */
    public function destroy(Request $request, AuditLogger $audit, \App\Services\AttendanceService $attendance)
    {
        $user = $request->user();
        if ($user && $user->role->portal() === 'employee') {
            $session = $attendance->todaySession($user);
            if ($session && $session->isOpen()) {
                return redirect()->route('employee.attendance.today')
                    ->with('error', 'You are still punched in. Punch out first to sign out — punch-out opens when your work score reaches 100%. If you must leave early, use Exception Checkout or ask admin.');
            }
        }

        $audit->log('auth.logout', 'Signed out of the portal');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You have been signed out.');
    }
}
