<?php

namespace App\Services;

use App\Enums\Role;
use App\Mail\MeetingInvite;
use App\Models\Meeting;
use App\Models\User;
use App\Support\MailPool;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Who is in a meeting (primary manager + other managers), who gets the invite (e-mail /
 * WhatsApp), the outcome (success / rejected / not connected) and contact privacy:
 * only the employee, admin and the primary manager see the customer's mobile and e-mail.
 */
class MeetingPeopleService
{
    public const OUTCOMES = [
        'success' => ['Success', 'success', 'check-circle-fill'],
        'rejected' => ['Rejected', 'danger', 'x-circle-fill'],
        'not_connected' => ['Not Connected', 'secondary', 'telephone-x-fill'],
    ];

    public function __construct(private WhatsAppGateway $wa) {}

    /** Managers / team leaders who can be added to meetings. */
    public static function managers(): Collection
    {
        return User::whereIn('role', [Role::Manager->value, Role::TeamLeader->value, Role::SuperAdmin->value, Role::Hr->value])
            ->where('status', 'active')->orderBy('name')->get(['id', 'name', 'employee_code', 'email', 'mobile', 'role']);
    }

    public function setParticipants(Meeting $m, ?int $primaryId, array $otherIds): void
    {
        $sync = [];
        foreach (array_unique(array_map('intval', $otherIds)) as $id) {
            if ($id && $id !== $primaryId) {
                $sync[$id] = ['role' => 'participant'];
            }
        }
        if ($primaryId) {
            $sync[$primaryId] = ['role' => 'primary'];
        }
        $m->participants()->sync($sync);
    }

    /** Send the invite to the given e-mails and WhatsApp numbers. Returns [{to, channel, ok, link?}]. */
    public function notify(Meeting $m, User $sender, array $emails, array $numbers): array
    {
        $results = [];
        $link = $m->joinLink();
        $text = "Meeting: {$m->title}\nDate & time: {$m->scheduled_at->format('d M Y, h:i A')}\nWith: {$sender->name}".($link ? "\nJoin here: {$link}" : '');

        foreach (array_unique(array_filter($emails, fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))) as $email) {
            $ok = MailPool::ready() && MailPool::send([$email], new MeetingInvite($m, $sender), "Meeting #{$m->id} invite");
            $results[] = ['to' => $email, 'channel' => 'email', 'ok' => $ok];
        }

        foreach (array_unique(array_filter(array_map(fn ($n) => preg_replace('/\D/', '', $n), $numbers))) as $number) {
            $ok = false;
            try {
                $res = $this->wa->call('POST', '/s/'.$this->wa->sid($sender).'/send', ['number' => $number, 'text' => $text]);
                $ok = ($res['ok'] ?? false) === true;
            } catch (RuntimeException $e) {
                $ok = false;
            }
            // Not linked / failed: a one-tap wa.me link is offered instead
            $results[] = ['to' => $number, 'channel' => 'whatsapp', 'ok' => $ok,
                'link' => $ok ? null : 'https://wa.me/'.(strlen($number) === 10 ? '91'.$number : $number).'?text='.rawurlencode($text)];
        }

        $m->forceFill(['notified' => array_merge((array) $m->notified, $results)])->saveQuietly();

        return $results;
    }

    public function canSeeContact(User $user, Meeting $m): bool
    {
        return $user->id === $m->user_id
            || $user->id === $m->created_by
            || $user->role->portal() === 'admin'
            || $m->participants->contains(fn ($p) => $p->id === $user->id && $p->pivot->role === 'primary');
    }

    /** Meetings nobody joined within 2 hours of their time are "Not Connected". */
    public static function closeMissed(): void
    {
        Meeting::where('status', 'upcoming')->whereNull('started_at')->where('scheduled_at', '<', now()->subHours(2))
            ->update(['status' => 'completed', 'outcome' => 'not_connected', 'duration_seconds' => 0, 'updated_at' => now()]);
    }

    public static function mask(?string $value): string
    {
        if (! $value) {
            return '—';
        }
        if (str_contains($value, '@')) {
            [$a, $d] = explode('@', $value, 2);

            return substr($a, 0, 1).'•••@'.$d;
        }

        return substr($value, 0, 2).'••••••'.substr($value, -2);
    }
}
