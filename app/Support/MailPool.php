<?php

namespace App\Support;

use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends through the alert mailboxes in rotation (config mail.pool), trying the next
 * mailbox when one fails. Never throws.
 */
class MailPool
{
    /** Mail can actually go out (a pool or a real default mailer is configured). */
    public static function ready(): bool
    {
        return config('mail.pool', []) !== [] || ! in_array(config('mail.default'), ['log', 'array'], true);
    }

    /** @return bool true when one of the mailboxes accepted the mail */
    public static function send(array $to, Mailable $mail, string $what = 'mail'): bool
    {
        $mailers = config('mail.pool', []) ?: [config('mail.default')];
        $start = count($mailers) > 1 ? (int) Cache::increment('mail:pool:next') % count($mailers) : 0;
        $order = array_merge(array_slice($mailers, $start), array_slice($mailers, 0, $start));

        foreach ($order as $mailer) {
            try {
                Mail::mailer($mailer)->to($to)->send($mail);

                return true;
            } catch (Throwable $e) {
                Log::error("{$what} failed via {$mailer}: ".$e->getMessage());
                // The recipient address itself was refused (e.g. 550 / 553 / 554): other mailboxes will fail too
                if (preg_match('/code "(550|551|553|554)"/', $e->getMessage())) {
                    return false;
                }
            }
        }

        return false;
    }
}
