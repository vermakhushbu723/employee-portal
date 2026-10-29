<?php

namespace App\Services;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LeadService
{
    public function __construct(private AuditLogger $audit) {}

    public function create(User $user, array $data): Lead
    {
        return DB::transaction(function () use ($user, $data) {
            $lead = Lead::create([
                ...$data,
                'department_id' => $data['department_id'] ?? $user->employee?->department_id,
                'created_by' => $user->id,
                'assigned_to' => $user->id,
                'status' => empty($data['follow_up_date']) ? LeadStatus::New : LeadStatus::FollowUp,
            ]);

            $lead->activities()->create([
                'user_id' => $user->id,
                'type' => 'created',
                'to_status' => $lead->status->value,
                'note' => $data['remarks'] ?? null,
            ]);

            $this->audit->log('lead.create', "Created lead {$lead->name}", ['lead_id' => $lead->id], $user);

            return $lead;
        });
    }

    public function updateStatus(Lead $lead, User $user, LeadStatus $status, ?string $followUpDate, ?string $note): void
    {
        $from = $lead->status;
        // "When to connect next": date + time (a date alone means 10:00 AM)
        $next = $followUpDate && in_array($status, [LeadStatus::FollowUp, LeadStatus::Callback], true)
            ? \Illuminate\Support\Carbon::parse(strlen($followUpDate) <= 10 ? $followUpDate.' 10:00' : $followUpDate) : null;

        $lead->update([
            'status' => $status,
            'follow_up_date' => $next ? $next->toDateString() : $lead->follow_up_date,
            'next_action_at' => $next ?? $lead->next_action_at,
            'next_action_type' => $next ? 'call' : $lead->next_action_type,
            'converted_at' => $status === LeadStatus::Converted ? ($lead->converted_at ?? now()) : null,
        ]);

        $lead->activities()->create([
            'user_id' => $user->id,
            'type' => $status === LeadStatus::FollowUp ? 'follow_up' : 'status_change',
            'from_status' => $from->value,
            'to_status' => $status->value,
            'note' => $note,
        ]);
    }
}
