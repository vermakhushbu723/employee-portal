<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
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
            'default_source' => ['nullable', 'string', 'max:50'],
            'default_interest' => ['nullable', 'string', 'max:100'],
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

        if (! empty($data['users']) && $batch->imported > 0) {
            $mode = $data['mode'] ?? 'equal';
            $given = $this->share->share($request->user(), $batch->id, $data['users'], $mode, (int) ($data['count'] ?? 0));
            $message .= ' Shared '.array_sum($given).' lead(s) with '.count($given).' employee(s).';
        } else {
            $message .= ' Not shared yet — use "Share Data" below.';
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
        ], ['users.required' => 'Select at least one employee to share data with.']);

        if ($data['mode'] === 'each' && (int) $data['count'] < 1) {
            return back()->withErrors(['count' => 'Enter how many leads each employee should get.'])->withInput();
        }

        $given = $this->share->share($request->user(), $data['batch_id'] ?? null, $data['users'], $data['mode'], (int) ($data['count'] ?? 0));
        $names = User::whereIn('id', array_keys($given))->pluck('name', 'id');
        $summary = collect($given)->map(fn ($n, $id) => "{$names[$id]} {$n}")->implode(', ');

        return back()->with('success', 'Shared '.array_sum($given)." lead(s): {$summary}. Employees can see it now.");
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
