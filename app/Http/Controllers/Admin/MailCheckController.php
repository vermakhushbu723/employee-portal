<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Admin: Email Check — sends one test mail through every alert mailbox and shows, per mailbox,
 * "Sent" or the exact error from the mail server (wrong password, blocked, refused address…).
 * Passwords are never shown.
 */
class MailCheckController extends Controller
{
    public function index()
    {
        return view('admin.mail-check', ['mailboxes' => $this->mailboxes(), 'results' => session('mail_results', [])]);
    }

    public function send(Request $request)
    {
        $to = $request->validate(['to' => ['required', 'email', 'max:150']])['to'];
        $results = [];
        foreach ($this->mailboxes() as $box) {
            $started = microtime(true);
            try {
                Mail::mailer($box['mailer'])->raw(
                    "This is a test e-mail from ".config('app.name')." sent through {$box['username']} at ".now()->format('d M Y, h:i:s A').'. If you can read this, this mailbox works.',
                    fn ($m) => $m->to($to)->subject('Test e-mail via '.$box['username'])
                );
                $results[$box['mailer']] = ['ok' => true, 'message' => 'Sent in '.round(microtime(true) - $started, 1).' s'];
            } catch (Throwable $e) {
                $results[$box['mailer']] = ['ok' => false, 'message' => mb_substr(preg_replace('/\s+/', ' ', $e->getMessage()), 0, 400)];
            }
        }

        return redirect()->route('admin.mail-check')->with('mail_results', $results)->withInput();
    }

    /** Pool mailboxes (alert1… from MAIL_POOL) or the single default mailer. */
    private function mailboxes(): array
    {
        $names = config('mail.pool', []) ?: [config('mail.default')];

        return array_map(fn ($name) => [
            'mailer' => $name,
            'username' => config("mail.mailers.{$name}.username") ?? config('mail.from.address'),
            'host' => config("mail.mailers.{$name}.host") ?? config("mail.mailers.{$name}.transport"),
            'port' => config("mail.mailers.{$name}.port"),
            'scheme' => config("mail.mailers.{$name}.scheme"),
            'has_password' => (bool) config("mail.mailers.{$name}.password"),
        ], $names);
    }
}
