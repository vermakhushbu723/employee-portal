<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Blocks sign-in through a VPN / proxy / hosting server, or from outside the allowed countries.
 * The IP is checked with proxycheck.io (free: 1,000 checks a day with a key, 100 without) and the
 * answer is cached for 12 hours per IP. If the check service cannot be reached, sign-in is allowed
 * (nobody is locked out because of an outside service). Super Admin is never blocked.
 */
class VpnGuard
{
    /** Reason the user may not sign in from this IP, or null when it is fine. */
    public function blockReason(User $user, ?string $ip): ?string
    {
        if (! config('portal.security.vpn_block') || ! $ip || $user->role === \App\Enums\Role::SuperAdmin) {
            return null;
        }
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;   // local network / this server
        }

        $info = $this->lookup($ip);
        if (! $info) {
            return null;
        }
        if ($info['proxy']) {
            return 'VPN / proxy detected ('.($info['type'] ?: 'proxy').'). Turn off the VPN and sign in again.';
        }
        $allowed = config('portal.security.allowed_countries', []);
        if ($allowed && $info['country'] && ! in_array($info['country'], $allowed, true)) {
            return 'Sign-in from outside '.implode(' / ', $allowed).' is not allowed. Turn off any VPN and sign in again.';
        }

        return null;
    }

    /** @return array{proxy: bool, type: ?string, country: ?string}|null */
    public function lookup(string $ip): ?array
    {
        return Cache::remember('vpn:'.$ip, now()->addHours(12), function () use ($ip) {
            try {
                $res = Http::timeout(4)->get("https://proxycheck.io/v2/{$ip}", array_filter([
                    'key' => config('portal.security.proxycheck_key'), 'vpn' => 1, 'asn' => 1,
                ]))->json();
                $row = $res[$ip] ?? null;
                if (($res['status'] ?? '') === 'error' || ! $row) {
                    Log::warning("VPN check failed for {$ip}: ".($res['message'] ?? 'no answer'));

                    return null;
                }

                return ['proxy' => ($row['proxy'] ?? 'no') === 'yes', 'type' => $row['type'] ?? null, 'country' => $row['isocode'] ?? null];
            } catch (Throwable $e) {
                Log::warning("VPN check failed for {$ip}: ".$e->getMessage());

                return null;
            }
        }) ?: null;
    }
}
