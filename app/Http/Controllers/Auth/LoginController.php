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

    public function store(LoginRequest $request, AuditLogger $audit, \App\Services\VpnGuard $vpn)
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

        // No sign-in through a VPN / proxy
        if ($reason = $vpn->blockReason($user, $request->ip())) {
            Auth::logout();
            $audit->log('auth.blocked', 'Sign-in blocked: '.$reason, ['ip' => $request->ip()], $user);
            throw ValidationException::withMessages(['employee_code' => $reason]);
        }

        // Location mandatory for this ID: the browser must send the position with the login
        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $hasLocation = is_numeric($lat) && is_numeric($lng) && abs((float) $lat) <= 90 && abs((float) $lng) <= 180;
        if ($user->location_required && ! $hasLocation) {
            Auth::logout();
            throw ValidationException::withMessages(['employee_code' => 'Location is mandatory for your ID. Allow location access for this site (lock icon in the address bar / app settings), turn on GPS and sign in again.']);
        }

        $request->session()->regenerate();
        $request->session()->put('net_ip', $request->ip());
        $user->forceFill(['last_login_at' => now()] + ($hasLocation ? [
            'latitude' => round((float) $lat, 7), 'longitude' => round((float) $lng, 7), 'location_at' => now(), 'location_status' => 'ok',
        ] : []))->save();
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
        if ($user && $user->tracksAttendance()) {
            $session = $attendance->todaySession($user);
            if ($session && $session->logoutPending()) {
                return redirect()->route('employee.attendance.today')
                    ->with('error', 'Your logout request is under review. You can sign out once your manager / admin accepts it.');
            }
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
