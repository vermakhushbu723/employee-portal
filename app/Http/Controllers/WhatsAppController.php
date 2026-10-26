<?php

namespace App\Http\Controllers;

use App\Services\WhatsAppGateway;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * WhatsApp inside the portal (side panel). Every user links and sees only their own WhatsApp:
 * the session id comes from the logged-in user, never from the request.
 */
class WhatsAppController extends Controller
{
    public function __construct(private WhatsAppGateway $wa) {}

    public function start(Request $request)
    {
        return $this->proxy(fn () => $this->wa->call('POST', '/s/'.$this->wa->sid($request->user()).'/start'));
    }

    public function status(Request $request)
    {
        return $this->proxy(fn () => $this->wa->call('GET', '/s/'.$this->wa->sid($request->user()).'/status'));
    }

    public function chats(Request $request)
    {
        $q = mb_substr((string) $request->query('q'), 0, 50);

        return $this->proxy(fn () => $this->started($request, fn ($sid) => $this->wa->call('GET', "/s/{$sid}/chats", ['q' => $q])));
    }

    public function messages(Request $request)
    {
        $jid = (string) $request->query('jid');
        abort_unless(preg_match('/^[0-9A-Za-z.:_-]{3,40}@(s\.whatsapp\.net|g\.us|lid)$/', $jid), 422);

        return $this->proxy(fn () => $this->started($request, fn ($sid) => $this->wa->call('GET', "/s/{$sid}/messages", ['jid' => $jid])));
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'jid' => ['nullable', 'string', 'max:60', 'regex:/^[0-9A-Za-z.:_-]{3,40}@(s\.whatsapp\.net|g\.us|lid)$/'],
            'number' => ['nullable', 'string', 'max:20'],
            'text' => ['required', 'string', 'max:4000'],
        ]);

        return $this->proxy(fn () => $this->started($request, fn ($sid) => $this->wa->call('POST', "/s/{$sid}/send", $data)));
    }

    public function logout(Request $request)
    {
        return $this->proxy(fn () => $this->started($request, fn ($sid) => $this->wa->call('POST', "/s/{$sid}/logout")));
    }

    /** The gateway forgets idle sessions; resume and retry once when it says "not started". */
    private function started(Request $request, callable $do): array
    {
        $sid = $this->wa->sid($request->user());
        $res = $do($sid);
        if (($res['_code'] ?? 200) === 409 && ($res['error'] ?? '') === 'session not started') {
            $this->wa->call('POST', "/s/{$sid}/start");
            $res = $do($sid);
        }

        return $res;
    }

    private function proxy(callable $do)
    {
        if (! config('portal.whatsapp.enabled')) {
            return response()->json(['error' => 'WhatsApp is not enabled.'], 503);
        }
        try {
            $res = $do();
        } catch (RuntimeException $e) {
            report($e);

            return response()->json(['error' => 'WhatsApp service is starting — try again in a moment.'], 503);
        }
        $code = $res['_code'] ?? 200;
        unset($res['_code']);

        return response()->json($res, $code >= 500 ? 502 : $code)->header('Cache-Control', 'no-store');
    }
}
