<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Announcement;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Task lifecycle: Assigned → In Progress → Submitted → Verified / Rejected. */
class TaskService
{
    public function __construct(private AuditLogger $audit) {}

    public function assign(User $assigner, array $data, array $userIds): Task
    {
        return DB::transaction(function () use ($assigner, $data, $userIds) {
            $task = Task::create([...$data, 'assigned_by' => $assigner->id]);

            foreach ($userIds as $userId) {
                $task->assignments()->create([
                    'user_id' => $userId,
                    'assigned_date' => $data['assigned_date'] ?? today()->toDateString(),
                ]);

                Announcement::create([
                    'user_id' => $userId,
                    'created_by' => $assigner->id,
                    'type' => 'general',
                    'title' => 'New Task Assigned',
                    'body' => "{$task->title} — target {$task->target_quantity} {$task->unit}.",
                ]);
            }

            $this->audit->log('task.assign', "Assigned \"{$task->title}\" to ".count($userIds).' employee(s)', ['task_id' => $task->id], $assigner);

            return $task;
        });
    }

    public function start(TaskAssignment $assignment, User $user): void
    {
        if (! in_array($assignment->status, [TaskStatus::Assigned, TaskStatus::Rejected], true)) {
            return;
        }

        $assignment->update(['status' => TaskStatus::InProgress, 'started_at' => $assignment->started_at ?? now()]);
        $this->activity($assignment, $user, 'started');
    }

    public function addProgress(TaskAssignment $assignment, User $user, int $quantity, ?string $note): void
    {
        $this->ensureEditable($assignment);
        $this->start($assignment, $user);

        $assignment->increment('completed_quantity', $quantity);
        $this->activity($assignment, $user, 'progress', $quantity, $note);
    }

    public function submit(TaskAssignment $assignment, User $user, ?string $note): void
    {
        $this->ensureEditable($assignment);

        if ($assignment->task->is_mandatory && $assignment->remaining() > 0) {
            throw ValidationException::withMessages([
                'action' => "Mandatory target not met yet: {$assignment->remaining()} {$assignment->task->unit} remaining.",
            ]);
        }

        $assignment->update(['status' => TaskStatus::Submitted, 'submitted_at' => now(), 'remarks' => $note]);
        $this->activity($assignment, $user, 'submitted', 0, $note);
        $this->audit->log('task.submit', "Submitted \"{$assignment->task->title}\"", ['assignment_id' => $assignment->id], $user);
    }

    private function ensureEditable(TaskAssignment $assignment): void
    {
        if ($assignment->status->isDone()) {
            throw ValidationException::withMessages(['action' => 'This task has already been submitted.']);
        }
    }

    private function activity(TaskAssignment $assignment, User $user, string $action, int $quantity = 0, ?string $note = null): void
    {
        $assignment->activities()->create([
            'user_id' => $user->id,
            'action' => $action,
            'quantity' => $quantity,
            'note' => $note,
        ]);
    }
}
