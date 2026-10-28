<?php

namespace App\Http\Middleware;

use App\Services\VpnGuard;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signed-in users are checked again whenever their IP changes (e.g. a VPN switched on after login):
 * a VPN / proxy IP signs them out.
 */
class BlockVpn
{
    public function __construct(private VpnGuard $vpn) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $request->session()->get('net_ip') === $request->ip()) {
            return $next($request);
        }

        if ($reason = $this->vpn->blockReason($user, $request->ip())) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['message' => $reason], 401)
                : redirect()->route('login')->withErrors(['employee_code' => $reason]);
        }
        $request->session()->put('net_ip', $request->ip());

        return $next($request);
    }
}
