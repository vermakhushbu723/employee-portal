<?php

namespace App\Services;

use App\Models\User;
use RuntimeException;

/**
 * Talks to the WhatsApp gateway (whatsapp-gateway/server.mjs) that keeps every user's linked
 * WhatsApp connected. On the server it listens on a socket file; in development on a localhost port.
 * If the gateway is not running, it is started automatically (and resumes all saved sessions).
 */
class WhatsAppGateway
{
    public function sid(User $user): string
    {
        return 'u'.$user->id;
    }

    public function call(string $method, string $path, array $data = [], bool $retry = true): array
    {
        try {
            return $this->request($method, $path, $data);
        } catch (RuntimeException $e) {
            if (! $retry || ! $this->start()) {
                throw $e;
            }

            return $this->request($method, $path, $data);
        }
    }

    private function request(string $method, string $path, array $data): array
    {
        $socket = config('portal.whatsapp.socket');
        $base = $socket ? 'http://localhost' : rtrim((string) config('portal.whatsapp.url'), '/');
        $url = $base.$path.($method === 'GET' && $data ? '?'.http_build_query($data) : '');

        $c = curl_init($url);
        curl_setopt_array($c, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => ['X-Gateway-Key: '.config('portal.whatsapp.key'), 'Content-Type: application/json'],
        ]);
        if ($socket) {
            curl_setopt($c, CURLOPT_UNIX_SOCKET_PATH, $socket);
        }
        if ($method !== 'GET') {
            curl_setopt($c, CURLOPT_POSTFIELDS, json_encode($data));
        }
        $body = curl_exec($c);
        $code = curl_getinfo($c, CURLINFO_RESPONSE_CODE);
        $error = curl_error($c);
        curl_close($c);

        if ($body === false || $code === 0) {
            throw new RuntimeException('WhatsApp service not reachable: '.$error);
        }

        $json = json_decode($body, true) ?: [];
        $json['_code'] = $code;

        return $json;
    }

    /** Start the gateway in the background (server). Returns true once it answers. */
    public function start(): bool
    {
        $dir = config('portal.whatsapp.dir');
        $node = config('portal.whatsapp.node');
        $socket = config('portal.whatsapp.socket');
        if (! $dir || ! $node || ! $socket || ! is_file($dir.'/server.mjs') || ! function_exists('exec')) {
            return false;
        }

        // Only one starter at a time. A lock *folder* (not a file handle) so the started
        // gateway cannot inherit it; a lock older than 30 s is from a crashed start.
        $lock = storage_path('framework/wa-gateway.lock.d');
        if (is_dir($lock) && time() - filemtime($lock) > 30) {
            @rmdir($lock);
        }
        $got = false;
        for ($i = 0; $i < 40 && ! ($got = @mkdir($lock)); $i++) {
            if ($this->alive()) {
                return true;   // the other starter finished
            }
            usleep(200000);
        }
        if (! $got) {
            return false;
        }

        try {
            if ($this->alive()) {
                return true;
            }
            // Fully detached: no output or input stays attached to PHP, so exec() returns at once
            $env = sprintf('WA_SOCKET=%s WA_KEY=%s WA_DATA=%s', escapeshellarg($socket), escapeshellarg((string) config('portal.whatsapp.key')), escapeshellarg($dir.'/data'));
            $run = sprintf('cd %s && exec env %s %s server.mjs', escapeshellarg($dir), $env, escapeshellarg($node));
            exec(sprintf('nohup sh -c %s >> %s 2>&1 < /dev/null &', escapeshellarg($run), escapeshellarg($dir.'/gateway.log')));

            for ($i = 0; $i < 20; $i++) {   // up to ~6 s
                usleep(300000);
                if ($this->alive()) {
                    return true;
                }
            }

            return false;
        } finally {
            @rmdir($lock);
        }
    }

    public function alive(): bool
    {
        try {
            return ($this->request('GET', '/health', [])['ok'] ?? false) === true;
        } catch (RuntimeException $e) {
            return false;
        }
    }
}
