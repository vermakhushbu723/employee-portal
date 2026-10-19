<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage in routes:  ->middleware('role:manager,team_leader')
 * Also signs out users whose account was deactivated.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user->isActive()) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['employee_code' => 'Your account is inactive. Please contact HR.']);
        }

        abort_unless(in_array($user->role->value, $roles, true), 403, 'You do not have access to this section.');

        return $next($request);
    }
}
