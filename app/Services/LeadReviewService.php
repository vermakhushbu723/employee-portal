<?php

namespace App\Services;

use App\Enums\LeadStatus;
use App\Models\Announcement;
use App\Models\AttendanceSession;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Review & correction of employees' lead work by admin / manager (Daily Work Report → Review).
 *   Admin: every lead. Manager / team leader: leads of their own team.
 *   They can change purpose of connect, status, next meeting / action time and remark.
 *   Moving a lead away from Interested / Converted lowers the employee's score (live and for
 *   closed days), and the employee sees a "Reviewed & corrected by …" tag on that lead.
 */
class LeadReviewService
{
    public function __construct(private WorkScoreService $score, private AuditLogger $audit) {}

    /** Leads this reviewer may see. */
    public function scope(User $reviewer): Builder
    {
        $q = Lead::query();
        if ($reviewer->role->portal() === 'admin') {
            return $q;
        }
        $team = $reviewer->teamMembers()->pluck('id');

        return $q->where(fn ($q) => $q->whereIn('assigned_to', $team)->orWhereIn('created_by', $team));
    }

    public function canReview(User $reviewer, Lead $lead): bool
    {
        return $this->scope($reviewer)->whereKey($lead->id)->exists();
    }

    /** @return array{changed: array, score: bool} */
    public function update(Lead $lead, User $reviewer, array $data): array
    {
        abort_unless($this->canReview($reviewer, $lead), 403);

        $changes = [];
        $from = $lead->status;
        $to = isset($data['status']) ? LeadStatus::from($data['status']) : $from;
        // A final result chosen in Next Action (Converted / Rejected / Invalid / Fake Lead / Irrelevant / User Not Attend) sets the status
        if (! isset($data['status']) && ($result = Lead::NEXT_ACTIONS[$data['next_action_type'] ?? ''][1] ?? null)) {
            $to = LeadStatus::from($result);
        }
        $labels = ['purpose' => 'Purpose', 'remarks' => 'Remark', 'next_action_at' => 'Next meeting / action', 'next_action_type' => 'Action type'];

        return DB::transaction(function () use ($lead, $reviewer, $data, $from, $to, $labels, &$changes) {
            foreach ($labels as $field => $label) {
                if (! array_key_exists($field, $data)) {
                    continue;
                }
                $old = $field === 'next_action_at' ? $lead->next_action_at?->format('Y-m-d H:i') : $lead->{$field};
                $new = $field === 'next_action_at' && $data[$field] ? Carbon::parse($data[$field])->format('Y-m-d H:i') : $data[$field];
                if ((string) $old !== (string) $new) {
                    $changes[] = "{$label}: ".($old ?: '—').' → '.($new ?: '—');
                    $lead->{$field} = $new ?: null;
                }
            }
            if ($to !== $from) {
                $changes[] = "Status: {$from->label()} → {$to->label()}";
                $lead->status = $to;
                $lead->converted_at = $to === LeadStatus::Converted ? ($lead->converted_at ?? now()) : null;
            }

            if (! $changes) {
                return ['changed' => [], 'score' => false];
            }

            $note = trim((string) ($data['note'] ?? ''));
            $lead->fill(['corrected_by' => $reviewer->id, 'corrected_at' => now(), 'correction_note' => $note ?: implode('; ', $changes)])->save();

            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => $reviewer->id,
                'type' => 'correction',
                'from_status' => $from->value,
                'to_status' => $to->value,
                'note' => 'Reviewed & corrected by '.($reviewer->role->portal() === 'admin' ? \App\Models\User::MANAGEMENT : $reviewer->name.' ('.$reviewer->roleLabel().')').': '.implode('; ', $changes).($note ? " — {$note}" : ''),
            ]);

            // Interested / Converted removed → the employee's score goes down
            $scoreHit = in_array($from->value, LeadStatus::positive(), true) && ! in_array($to->value, LeadStatus::positive(), true);
            if ($scoreHit) {
                $this->rescoreAffectedDays($lead);
            }

            $employeeId = $lead->assigned_to ?: $lead->created_by;
            if ($employeeId && $employeeId !== $reviewer->id) {
                Announcement::create([
                    'user_id' => $employeeId,
                    'created_by' => $reviewer->id,
                    'type' => $scoreHit ? 'important' : 'general',
                    'title' => 'Lead reviewed & corrected by '.($reviewer->role->portal() === 'admin' ? \App\Models\User::MANAGEMENT : 'Manager'),
                    'body' => "{$lead->name}: ".implode('; ', $changes).($scoreHit ? "\nThis was not a correct Interested / Converted marking — your work score was updated." : '').($note ? "\nNote: {$note}" : ''),
                ]);
            }

            $this->audit->log('lead.review', "Corrected lead #{$lead->id} {$lead->name}: ".implode('; ', $changes), ['lead_id' => $lead->id], $reviewer);

            return ['changed' => $changes, 'score' => $scoreHit];
        });
    }

    /** Days on which an employee counted this lead as interested / converted: closed days are re-scored. */
    private function rescoreAffectedDays(Lead $lead): void
    {
        $marks = LeadActivity::where('lead_id', $lead->id)->where('type', '!=', 'correction')
            ->whereIn('to_status', LeadStatus::positive())
            ->get(['user_id', 'created_at'])
            ->unique(fn ($a) => $a->user_id.'|'.$a->created_at->toDateString());

        foreach ($marks as $mark) {
            $session = AttendanceSession::with('user', 'breaks')->where('user_id', $mark->user_id)
                ->whereDate('work_date', $mark->created_at->toDateString())->first();
            if (! $session || $session->grade === null) {
                continue; // today / open: the live score already leaves the lead out
            }
            if ($session->corrected_at) {
                // Numbers were set by a reviewed work correction: take this lead off them
                $session->interested_count = max(0, (int) $session->interested_count - 1);
                $this->score->rescoreStored($session);
            } else {
                $this->score->finalize($session);
            }
        }
    }
}
