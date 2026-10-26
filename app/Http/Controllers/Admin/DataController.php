<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\DataShare;
use App\Models\Department;
use App\Models\Lead;
use App\Models\LeadBatch;
use App\Models\User;
use App\Services\DataImportService;
use App\Services\DataShareService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Admin → Data Upload & Share (calling lists). */
class DataController extends Controller
{
    public function __construct(private DataShareService $share) {}

    public function index(Request $request)
    {
        $batchId = $request->integer('batch') ?: null;
        $uploaded = Lead::whereNotNull('batch_id');

        return view('admin.data.index', [
            'batchId' => $batchId,
            'stats' => [
                'total' => (clone $uploaded)->count(),
                'pool' => (clone $uploaded)->whereNull('assigned_to')->count(),
                'assigned' => (clone $uploaded)->whereNotNull('assigned_to')->count(),
                'worked' => (clone $uploaded)->whereNotNull('assigned_to')->where('status', '!=', LeadStatus::New->value)->count(),
                'converted' => (clone $uploaded)->where('status', LeadStatus::Converted->value)->count(),
            ],
            'batches' => LeadBatch::with('uploader', 'department')
                ->withCount([
                    'leads as pool_count' => fn ($q) => $q->whereNull('assigned_to'),
                    'leads as assigned_count' => fn ($q) => $q->whereNotNull('assigned_to'),
                    'leads as worked_count' => fn ($q) => $q->whereNotNull('assigned_to')->where('status', '!=', LeadStatus::New->value),
                ])
                ->latest()
                ->limit(20)
                ->get(),
            'recipients' => DataShareService::recipients(),
            'distribution' => DataShareService::distribution($batchId),
            'options' => config('portal.lead'),
            // For the upload form: departments, their managers, and every employee (with department + manager)
            'departments' => Department::forData()->get(),
            'managers' => User::whereIn('role', [Role::Manager->value, Role::TeamLeader->value])->with('employee')->orderBy('name')->get(),
            'staff' => User::whereIn('role', [Role::Employee->value, Role::TeamLeader->value])->with('employee.department')->orderBy('name')->get(),
            'shares' => DataShare::with('sharer', 'batch', 'manager', 'rolledBackBy')
                ->withCount(['leads as called_count' => fn ($q) => $q->where('status', '!=', LeadStatus::New->value)])
                ->latest()->limit(15)->get(),
        ]);
    }

    /**
     * Upload & share in one step: department + manager, employees, selected columns and the file.
     * (The browser converts .xls/.xlsx to CSV before sending, so any Excel version works.)
     */
    public function upload(Request $request, DataImportService $importer)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'extensions:csv,txt,xlsx'],
            'original_name' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:100'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string', 'max:100'],
            'users' => ['nullable', 'array'],
            'users.*' => ['integer', 'exists:users,id'],
            'mode' => ['nullable', Rule::in(['equal', 'each'])],
            'count' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'work_message' => ['nullable', 'string', 'max:1000'],
            'interested_target' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'default_source' => ['nullable', 'string', 'max:50'],
            'default_interest' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', Rule::in(['share', 'upload'])],
        ], [
            'department_id.required' => 'Select a department for this data.',
            'file.extensions' => 'Upload a CSV or Excel file.',
        ]);

        $department = Department::find($data['department_id']);
        $batch = $importer->import(
            $request->user(),
            $request->file('file'),
            ($data['name'] ?? null) ?: $department->name.' Data '.now()->format('d M h:i A'),
            $data['default_source'] ?? 'Data Upload',
            $data['default_interest'] ?? $department->name,
            $data['columns'] ?? null,
            $department->id,
            $data['manager_id'] ?? null,
            $data['original_name'] ?? null,
        );

        $message = "\"{$batch->name}\" uploaded: {$batch->imported} new leads with ".count($batch->columns ?? []).' field(s)'.
            ($batch->duplicates ? ", {$batch->duplicates} duplicate skipped" : '').
            ($batch->invalid ? ", {$batch->invalid} invalid number(s) skipped" : '').'.';

        $onlyUpload = ($data['action'] ?? 'share') === 'upload';
        if (! $onlyUpload && ! empty($data['users']) && $batch->imported > 0) {
            $mode = $data['mode'] ?? 'equal';
            $given = $this->share->share($request->user(), $batch->id, $data['users'], $mode, (int) ($data['count'] ?? 0), $data['work_message'] ?? null, (int) ($data['interested_target'] ?? 0), $data['manager_id'] ?? null);
            $message .= ' Shared '.array_sum($given).' lead(s) with '.count($given).' employee(s).';
        } else {
            $message .= ' Not shared yet — use "Share Unassigned Data" below.';
        }

        return redirect()->route('admin.data.index', ['batch' => $batch->id])->with('success', $message);
    }

    public function template()
    {
        $rows = [
            ['Name', 'Mobile', 'Alternate Number', 'Email', 'City', 'State', 'Source', 'Interest', 'Remarks'],
            ['Rahul Singh', '9876543210', '', 'rahul@example.com', 'Delhi', 'Delhi', 'Website', 'Home Loan', 'Call after 11 AM'],
            ['Priya Khanna', '+91 98765 43211', '', '', 'Gurugram', 'Haryana', 'Facebook', 'Personal Loan', ''],
        ];

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens it cleanly
            foreach ($rows as $row) {
                fputcsv($out, $row, ',', '"', '');
            }
            fclose($out);
        }, 'calling-data-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function share(Request $request)
    {
        $data = $request->validate([
            'batch_id' => ['nullable', 'integer', 'exists:lead_batches,id'],
            'users' => ['required', 'array', 'min:1'],
            'users.*' => ['integer', 'exists:users,id'],
            'mode' => ['required', Rule::in(['equal', 'each'])],
            'count' => ['nullable', 'integer', 'min:0', 'max:5000', Rule::requiredIf($request->input('mode') === 'each')],
            'work_message' => ['nullable', 'string', 'max:1000'],
            'interested_target' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
        ], ['users.required' => 'Select at least one employee to share data with.']);

        if ($data['mode'] === 'each' && (int) $data['count'] < 1) {
            return back()->withErrors(['count' => 'Enter how many leads each employee should get.'])->withInput();
        }

        $given = $this->share->share($request->user(), $data['batch_id'] ?? null, $data['users'], $data['mode'], (int) ($data['count'] ?? 0), $data['work_message'] ?? null, (int) ($data['interested_target'] ?? 0), $data['manager_id'] ?? null);
        $names = User::whereIn('id', array_keys($given))->pluck('name', 'id');
        $summary = collect($given)->map(fn ($n, $id) => "{$names[$id]} {$n}")->implode(', ');

        return back()->with('success', 'Shared '.array_sum($given)." lead(s): {$summary}. Employees can see it now.");
    }

    /** Lead-creation task (admin: any employee). Managers use the same form for their own team. */
    public function leadTask(Request $request, \App\Services\WorkAssignmentService $assignments)
    {
        $allowed = $request->user()->role->portal() === 'admin'
            ? DataShareService::recipients()->pluck('id')->all()
            : $request->user()->teamMembers()->pluck('id')->all();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'lead_target' => ['required', 'integer', 'min:1', 'max:1000'],
            'message' => ['nullable', 'string', 'max:1000'],
            'task_users' => ['required', 'array', 'min:1'],
            'task_users.*' => ['integer', Rule::in($allowed)],
        ], [
            'task_users.required' => 'Select at least one employee for the lead creation task.',
            'task_users.*.in' => 'You can only give tasks to your own employees.',
        ]);

        $n = $assignments->createLeadTask($request->user(), $data['task_users'], $data['title'], (int) $data['lead_target'], $data['message'] ?? null);

        return back()->with('success', "Lead creation task \"{$data['title']}\" given to {$n} employee(s). It shows on their dashboard now.");
    }

    /** Recent Data Shares → Rollback (un-called leads go back to the pool). */
    public function rollback(Request $request, DataShare $share)
    {
        $returned = $this->share->rollback($request->user(), $share);
        $kept = $share->total - $returned;

        return back()->with('success', "Share rolled back: {$returned} un-called lead(s) are back in the unassigned pool."
            .($kept > 0 ? " {$kept} already-called lead(s) stay with the employees." : ''));
    }

    public function recall(Request $request, User $user)
    {
        $batchId = $request->integer('batch_id') ?: null;
        $count = $this->share->recall($request->user(), $user, $batchId);

        return back()->with('success', "Took back {$count} un-called lead(s) from {$user->name}. They are back in the unassigned pool.");
    }

    public function show(Request $request, LeadBatch $batch)
    {
        $status = LeadStatus::tryFrom((string) $request->query('status'));
        $assignee = $request->query('assigned');

        return view('admin.data.show', [
            'batch' => $batch->load('uploader'),
            'status' => $status,
            'leads' => $batch->leads()->with('assignee')
                ->when($status, fn ($q) => $q->where('status', $status))
                ->when($assignee === 'none', fn ($q) => $q->whereNull('assigned_to'))
                ->when(is_numeric($assignee), fn ($q) => $q->where('assigned_to', (int) $assignee))
                ->orderBy('id')
                ->paginate(25)
                ->withQueryString(),
            'recipients' => DataShareService::recipients(),
        ]);
    }
}
