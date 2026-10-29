<?php

namespace App\Http\Controllers;

use App\Mail\EmailOtp;
use App\Models\UserEmail;
use App\Support\MailPool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * My Profile → My E-mail IDs: every user (employee, HR, manager…) connects up to 4 of their own
 * e-mail IDs. Each one is confirmed with a 6-digit code sent to it. The primary verified ID is used
 * as "Reply to" on e-mails sent from the portal, so customers' answers reach the employee.
 */
class UserEmailController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();
        $email = strtolower(trim($request->validate(['email' => ['required', 'email:rfc', 'max:150']])['email']));

        $row = UserEmail::firstWhere(['user_id' => $user->id, 'email' => $email]);
        if (! $row && $user->emails()->count() >= UserEmail::MAX_PER_USER) {
            throw ValidationException::withMessages(['email' => 'You can connect up to '.UserEmail::MAX_PER_USER.' e-mail IDs. Remove one first.']);
        }
        if ($row?->isVerified()) {
            return back()->with('success', "{$email} is already connected.");
        }
        $row ??= UserEmail::create(['user_id' => $user->id, 'email' => $email]);

        return $this->sendCode($row, $user->name);
    }

    public function resend(Request $request, UserEmail $email)
    {
        $this->own($request, $email);
        if ($email->otp_sent_at && $email->otp_sent_at->gt(now()->subMinute())) {
            return back()->withErrors(['otp' => 'Please wait a minute before asking for a new code.'])->with('otp_for', $email->id);
        }

        return $this->sendCode($email, $request->user()->name);
    }

    public function verify(Request $request, UserEmail $email)
    {
        $this->own($request, $email);
        $code = preg_replace('/\D/', '', (string) $request->validate(['code' => ['required', 'string', 'max:10']])['code']);

        $fail = fn (string $msg) => back()->withErrors(['otp' => $msg])->with('otp_for', $email->id);
        if (! $email->otp_hash || ! $email->otp_expires_at || $email->otp_expires_at->isPast()) {
            return $fail('This code has expired. Tap "Send new code".');
        }
        if ($email->otp_attempts >= 5) {
            return $fail('Too many wrong codes. Tap "Send new code".');
        }
        if (! Hash::check($code, $email->otp_hash)) {
            $email->increment('otp_attempts');

            return $fail('Wrong code. Check the latest e-mail and try again.');
        }

        $first = ! $request->user()->emails()->whereNotNull('verified_at')->exists();
        $email->update(['verified_at' => now(), 'otp_hash' => null, 'otp_expires_at' => null, 'otp_attempts' => 0, 'is_primary' => $first]);

        return back()->with('success', "{$email->email} connected.".($first ? ' It is your primary e-mail ID.' : ''));
    }

    public function primary(Request $request, UserEmail $email)
    {
        $this->own($request, $email);
        abort_unless($email->isVerified(), 422, 'Verify this e-mail ID first.');
        $request->user()->emails()->update(['is_primary' => false]);
        $email->update(['is_primary' => true]);

        return back()->with('success', "{$email->email} is now your primary e-mail ID.");
    }

    public function destroy(Request $request, UserEmail $email)
    {
        $this->own($request, $email);
        $wasPrimary = $email->is_primary;
        $email->delete();
        if ($wasPrimary) {
            $request->user()->emails()->whereNotNull('verified_at')->oldest()->first()?->update(['is_primary' => true]);
        }

        return back()->with('success', "{$email->email} removed.");
    }

    private function sendCode(UserEmail $row, string $name)
    {
        $code = (string) random_int(100000, 999999);
        $row->update(['otp_hash' => Hash::make($code), 'otp_expires_at' => now()->addMinutes(10), 'otp_attempts' => 0, 'otp_sent_at' => now()]);

        $sent = MailPool::ready() && MailPool::send([$row->email], new EmailOtp($code, $name), "E-mail OTP for user #{$row->user_id}");
        if (! $sent) {
            return back()->withErrors(['email' => "Could not send the code to {$row->email}. Check the address — if it is right, the portal e-mail is not working: ask admin to open Email Check."])->with('otp_for', $row->id);
        }

        return back()->with('success', "Code sent to {$row->email}. Enter it below (valid for 10 minutes; check Spam too).")->with('otp_for', $row->id);
    }

    private function own(Request $request, UserEmail $email): void
    {
        abort_unless($email->user_id === $request->user()->id, 403);
    }
}
