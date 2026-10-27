<?php

namespace App\Services;

use App\Mail\LeadEmail;
use App\Models\Lead;
use App\Models\LeadCommunication;
use App\Models\User;
use App\Support\MailPool;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Calls, WhatsApp, e-mail and voice notes on a lead, all logged in lead_communications
 * (lead history, Talk Time / Mic columns, Emails / WhatsApp Sent in the daily report).
 */
class LeadCommunicationService
{
    public function __construct(private WhatsAppGateway $wa) {}

    /** Fill {Name} {Date} {Time} {Employee} {Company} {Purpose}. */
    public static function fill(string $text, Lead $lead, User $user): string
    {
        $next = $lead->next_action_at;

        return strtr($text, [
            '{Name}' => $lead->name,
            '{Date}' => $next?->format('d M Y') ?? now()->format('d M Y'),
            '{Time}' => $next?->format('h:i A') ?? '',
            '{Employee}' => $user->name,
            '{Company}' => config('portal.company'),
            '{Purpose}' => $lead->purpose ?: $lead->interest ?: 'our service',
        ]);
    }

    /** Templates for this lead (HR wording for HR leads), already filled in. */
    public static function templates(Lead $lead, User $user): array
    {
        $set = config('portal.message_templates.'.($lead->isHr() ? 'hr' : 'general'));

        return [
            'whatsapp' => collect($set['whatsapp'])->map(fn ($t) => self::fill($t, $lead, $user))->all(),
            'email' => collect($set['email'])->map(fn ($t) => ['subject' => self::fill($t[0], $lead, $user), 'body' => self::fill($t[1], $lead, $user)])->all(),
        ];
    }

    public function startCall(Lead $lead, User $user): LeadCommunication
    {
        return LeadCommunication::create(['lead_id' => $lead->id, 'user_id' => $user->id, 'channel' => 'call', 'status' => 'started']);
    }

    /** Talk time is measured in the browser (tap Call → back in the portal); capped at 2 hours. */
    public function endCall(LeadCommunication $call, int $seconds, ?string $note): LeadCommunication
    {
        $call->update(['status' => 'done', 'duration_seconds' => max(0, min(7200, $seconds)), 'body' => $note]);

        return $call;
    }

    /** Sends from the employee's own linked WhatsApp. Returns [communication, fallback wa.me link|null]. */
    public function whatsapp(Lead $lead, User $user, string $text): array
    {
        $ok = false;
        try {
            $res = $this->wa->call('POST', '/s/'.$this->wa->sid($user).'/send', ['number' => $lead->mobile, 'text' => $text]);
            $ok = ($res['ok'] ?? false) === true;
        } catch (RuntimeException $e) {
            $ok = false;
        }
        $comm = LeadCommunication::create(['lead_id' => $lead->id, 'user_id' => $user->id, 'channel' => 'whatsapp', 'status' => $ok ? 'sent' : 'failed', 'body' => $text]);
        $digits = preg_replace('/\D/', '', $lead->mobile);
        $link = $ok ? null : 'https://wa.me/'.(strlen($digits) === 10 ? '91'.$digits : $digits).'?text='.rawurlencode($text);

        return [$comm, $link];
    }

    public function email(Lead $lead, User $user, string $to, string $subject, string $body, ?UploadedFile $file): LeadCommunication
    {
        $path = $file?->store('lead-files/'.$lead->id, 'local');
        $ok = MailPool::ready() && MailPool::send([$to], new LeadEmail($subject, $body, $user, $path ? storage_path('app/private/'.$path) : null, $file?->getClientOriginalName()), "Lead #{$lead->id} e-mail");

        return LeadCommunication::create([
            'lead_id' => $lead->id, 'user_id' => $user->id, 'channel' => 'email', 'status' => $ok ? 'sent' : 'failed',
            'subject' => $subject, 'body' => $body, 'file_path' => $path,
        ]);
    }

    public function voiceNote(Lead $lead, User $user, UploadedFile $audio, int $seconds): LeadCommunication
    {
        $path = $audio->store('voice-notes/'.$lead->id, 'local');

        return LeadCommunication::create([
            'lead_id' => $lead->id, 'user_id' => $user->id, 'channel' => 'voice_note', 'status' => 'done',
            'duration_seconds' => max(0, min(3600, $seconds)), 'file_path' => $path,
        ]);
    }
}
