<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Services\DataShareService;
use Illuminate\Http\Request;

/** Admin → Interested Leads: every employee's leads with status "Interested". */
class InterestedLeadController extends Controller
{
    public function __invoke(Request $request)
    {
        $employeeId = $request->integer('employee') ?: null;
        $search = trim((string) $request->query('q'));

        $query = Lead::query()
            ->with(['assignee', 'creator', 'latestActivity'])
            ->where('status', LeadStatus::Interested->value)
            ->when($employeeId, fn ($q) => $q->where(fn ($q) => $q->where('assigned_to', $employeeId)
                ->orWhere(fn ($q) => $q->whereNull('assigned_to')->where('created_by', $employeeId))))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('mobile', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%")))
            ->latest('updated_at');

        if ($request->boolean('export')) {
            return $this->export($query->get());
        }

        return view('admin.leads.interested', [
            'leads' => $query->paginate(25)->withQueryString(),
            'employees' => DataShareService::recipients(),
            'employeeId' => $employeeId,
            'total' => Lead::where('status', LeadStatus::Interested->value)->count(),
            'today' => Lead::where('status', LeadStatus::Interested->value)->whereDate('updated_at', today())->count(),
        ]);
    }

    private function export($leads)
    {
        return response()->streamDownload(function () use ($leads) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Customer', 'Mobile', 'Alternate', 'Email', 'City', 'Interest', 'Employee', 'Employee ID', 'Last Remark', 'Interested On'], ',', '"', '');
            foreach ($leads as $lead) {
                $owner = $lead->assignee ?? $lead->creator;
                fputcsv($out, [
                    $lead->name, $lead->mobile, $lead->alternate_mobile, $lead->email, $lead->city, $lead->interest,
                    $owner?->name, $owner?->employee_code, $lead->latestActivity?->note, $lead->updated_at->format('d-m-Y h:i A'),
                ], ',', '"', '');
            }
            fclose($out);
        }, 'interested-leads-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
