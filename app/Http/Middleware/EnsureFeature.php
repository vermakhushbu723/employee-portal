<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Employee sections (punch in / out, leads, meetings): every employee has them; a manager or
 * team leader only when admin ticked that section on their profile (users.features).
 */
class EnsureFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless($request->user()?->hasFeature($feature), 403, 'This section is not enabled for your account.');

        return $next($request);
    }
}
