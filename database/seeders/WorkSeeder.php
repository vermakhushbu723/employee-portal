<?php

namespace Database\Seeders;

use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\Announcement;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Targets, today's tasks, call history, leads and announcements. */
class WorkSeeder extends Seeder
{
    private const FIRST = ['Rahul', 'Priya', 'Amit', 'Sneha', 'Vikram', 'Pooja', 'Karan', 'Anjali', 'Rohan', 'Meera', 'Arjun', 'Kavya', 'Nikhil', 'Isha', 'Sahil'];
    private const LAST = ['Singh', 'Khanna', 'Mehta', 'Nair', 'Kumar', 'Tiwari', 'Malhotra', 'Joshi', 'Verma', 'Reddy', 'Chopra', 'Bansal'];

    private int $mobile = 9876543210;

    public function run(): void
    {
        $manager = User::where('employee_code', 'MGR001')->first();
        $employees = User::where('role', Role::Employee)->orderBy('employee_code')->get();

        foreach ($employees as $user) {
            foreach (['calls' => 450, 'leads' => 50, 'follow_ups' => 40, 'conversions' => 15] as $metric => $value) {
                $user->targets()->create(['metric' => $metric, 'period' => 'monthly', 'target_value' => $value]);
            }
        }

        $this->todaysTasks($manager, $employees);
        $this->callHistory($manager, $employees);

        foreach ($employees as $index => $user) {
            $this->leads($user, $index === 0 ? 40 : 12, $index === 0);
        }

        $this->announcements($manager);
    }

    private function todaysTasks(User $manager, $employees): void
    {
        $deadline = today()->setTime(18, 30);

        $tasks = [
            [[
                'title' => 'Outbound Calling', 'category' => 'telecalling', 'priority' => 'high',
                'description' => 'Call assigned leads from list and update call status in system.',
                'target_quantity' => 20, 'unit' => 'Calls', 'deadline_at' => $deadline, 'is_mandatory' => true,
                'related_list' => 'Assigned Leads (25)',
                'rules' => [
                    'Minimum 20 calls required.',
                    'Each call must be updated with status.',
                    'At least 5 follow-ups must be scheduled.',
                    'Call recording or remarks are mandatory.',
                ],
            ], [12, 9, 15], TaskStatus::InProgress],
            [[
                'title' => 'Lead Generation', 'category' => 'lead_generation', 'priority' => 'medium',
                'description' => 'Create new leads in system.', 'target_quantity' => 15, 'unit' => 'Leads', 'deadline_at' => $deadline,
            ], [8, 6, 3], TaskStatus::InProgress],
            [[
                'title' => 'Follow-up Calls', 'category' => 'follow_up', 'priority' => 'medium',
                'description' => 'Follow-up with interested leads.', 'target_quantity' => 10, 'unit' => 'Follow-ups', 'deadline_at' => $deadline,
            ], [4, 2, 0], TaskStatus::Assigned],
            [[
                'title' => 'Data Update', 'category' => 'data_entry', 'priority' => 'low',
                'description' => 'Update customer records.', 'target_quantity' => 5, 'unit' => 'Records', 'deadline_at' => today()->subDay()->setTime(18, 30),
            ], [3, 1, 0], TaskStatus::InProgress],
            [[
                'title' => 'Daily Report', 'category' => 'report', 'priority' => 'medium',
                'description' => 'Submit daily work summary.', 'target_quantity' => 1, 'unit' => 'Report', 'deadline_at' => today()->setTime(19, 0),
            ], [0, 0, 0], TaskStatus::Assigned],
        ];

        foreach ($tasks as [$data, $done, $status]) {
            $task = Task::create([...$data, 'assigned_by' => $manager->id]);

            foreach ($employees as $i => $user) {
                $qty = $done[$i] ?? 0;
                $assignment = $task->assignments()->create([
                    'user_id' => $user->id,
                    'assigned_date' => today()->toDateString(),
                    'completed_quantity' => $qty,
                    'status' => $qty > 0 ? $status : TaskStatus::Assigned,
                    'started_at' => $qty > 0 ? today()->setTime(9, 45) : null,
                    'created_at' => today()->setTime(9, 0),
                ]);

                if ($qty > 0) {
                    $assignment->activities()->create(['user_id' => $user->id, 'action' => 'started', 'created_at' => today()->setTime(9, 45)]);

                    // Log the completed quantity in chunks through the morning.
                    $left = $qty;
                    $hour = 10;
                    while ($left > 0) {
                        $chunk = min(4, $left);
                        $assignment->activities()->create([
                            'user_id' => $user->id, 'action' => 'progress', 'quantity' => $chunk,
                            'note' => 'Updated status in system', 'created_at' => today()->setTime(min($hour++, 23), 15),
                        ]);
                        $left -= $chunk;
                    }
                }
            }
        }
    }

    /** One long-running calling campaign with a progress entry per worked day. */
    private function callHistory(User $manager, $employees): void
    {
        $campaign = Task::create([
            'title' => 'Outbound Calling — Monthly Campaign', 'category' => 'telecalling', 'priority' => 'medium',
            'description' => 'Ongoing outbound calling campaign.', 'target_quantity' => 450, 'unit' => 'Calls',
            'assigned_by' => $manager->id,
        ]);

        foreach ($employees as $i => $user) {
            $assignment = $campaign->assignments()->create([
                'user_id' => $user->id,
                'assigned_date' => now()->subMonthsNoOverflow(2)->startOfMonth()->toDateString(),
                'status' => TaskStatus::Verified,
                'verified_at' => today()->subDay(),
            ]);

            $total = 0;
            $sessions = $user->attendanceSessions()->whereDate('work_date', '<', today())->get();

            foreach ($sessions as $session) {
                $calls = 14 + ($session->work_date->day * 7 + $i * 5) % 12;
                $total += $calls;

                $assignment->activities()->create([
                    'user_id' => $user->id, 'action' => 'progress', 'quantity' => $calls,
                    'note' => 'Daily calls', 'created_at' => $session->work_date->copy()->setTime(17, 30),
                ]);
            }

            $assignment->update(['completed_quantity' => $total]);
        }
    }

    private function leads(User $user, int $count, bool $featured): void
    {
        $cities = config('portal.lead.cities');
        $sources = config('portal.lead.sources');
        $interests = config('portal.lead.interests');

        // The six leads shown in the design, created recently.
        $named = $featured ? [
            ['Rahul Singh', 'Delhi', LeadStatus::Interested],
            ['Priya Khanna', 'Gurugram', LeadStatus::FollowUp],
            ['Amit Mehta', 'Noida', LeadStatus::NotInterested],
            ['Sneha Nair', 'Mumbai', LeadStatus::Converted],
            ['Vikram Kumar', 'Bengaluru', LeadStatus::Invalid],
            ['Pooja Tiwari', 'Lucknow', LeadStatus::FollowUp],
        ] : [];

        $statuses = [LeadStatus::New, LeadStatus::Interested, LeadStatus::FollowUp, LeadStatus::Converted, LeadStatus::NotInterested, LeadStatus::Interested, LeadStatus::Invalid];

        for ($i = 0; $i < $count; $i++) {
            if (isset($named[$i])) {
                [$name, $city, $status] = $named[$i];
                $created = now()->subDays($i)->setTime(11, 10 + $i);
            } else {
                $name = self::FIRST[($i * 7 + $user->id) % count(self::FIRST)].' '.self::LAST[($i * 5 + $user->id) % count(self::LAST)];
                $city = $cities[$i % count($cities)];
                $status = $statuses[$i % count($statuses)];
                $created = now()->subDays(($i * 3) % 55 + 1)->setTime(10 + $i % 7, ($i * 11) % 60);
            }

            $lead = Lead::create([
                'created_by' => $user->id,
                'assigned_to' => $user->id,
                'name' => $name,
                'mobile' => (string) $this->mobile--,
                'city' => $city,
                'state' => null,
                'source' => $sources[$i % count($sources)],
                'interest' => $interests[$i % count($interests)],
                'remarks' => 'Customer asked for details on WhatsApp.',
                'status' => $status,
                'follow_up_date' => $status === LeadStatus::FollowUp ? today()->addDays(1 + $i % 3)->toDateString() : null,
                'converted_at' => $status === LeadStatus::Converted ? $created->copy()->addDays(2)->min(now()) : null,
                'created_at' => $created,
                'updated_at' => $created,
            ]);

            $lead->activities()->create(['user_id' => $user->id, 'type' => 'created', 'to_status' => 'new', 'created_at' => $created]);

            if ($status !== LeadStatus::New) {
                $lead->activities()->create([
                    'user_id' => $user->id,
                    'type' => in_array($status, [LeadStatus::FollowUp, LeadStatus::Interested, LeadStatus::Converted], true) ? 'follow_up' : 'status_change',
                    'from_status' => 'new',
                    'to_status' => $status->value,
                    'note' => 'Spoke to customer.',
                    'created_at' => $created->copy()->addDay()->min(now()),
                ]);
            }
        }
    }

    private function announcements(User $manager): void
    {
        Announcement::create([
            'created_by' => $manager->id, 'type' => 'important', 'title' => 'New Campaign Start',
            'body' => 'Outbound calling campaign started. Target: 20 calls per day.', 'published_at' => today()->setTime(9, 0),
        ]);
        Announcement::create([
            'created_by' => $manager->id, 'type' => 'general', 'title' => 'Lead Source Update',
            'body' => 'Please use updated lead source list from today onwards.', 'published_at' => today()->subDay()->setTime(17, 30),
        ]);
        Announcement::create([
            'created_by' => $manager->id, 'type' => 'alert', 'title' => 'Attendance Reminder',
            'body' => 'Complete daily working hours and submit all tasks before logout.', 'published_at' => today()->subDays(2)->setTime(18, 0),
        ]);
    }
}
