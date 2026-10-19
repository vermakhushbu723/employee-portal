<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeaveType;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeRequest;
use App\Models\Department;
use App\Models\Shift;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q'));

        $users = User::with('employee.department', 'employee.manager')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('employee_code', 'like', "%{$search}%")
                ->orWhere('mobile', 'like', "%{$search}%")))
            ->when(Role::tryFrom((string) $request->query('role')), fn ($q, $role) => $q->where('role', $role))
            ->orderBy('employee_code')
            ->paginate(15)
            ->withQueryString();

        return view('admin.employees.index', ['users' => $users, 'roles' => Role::cases()]);
    }

    public function create()
    {
        return view('admin.employees.create', [
            'roles' => Role::cases(),
            'departments' => Department::orderBy('name')->get(),
            'shifts' => Shift::orderBy('start_time')->get(),
            'managers' => User::whereIn('role', [Role::Manager->value, Role::TeamLeader->value])->orderBy('name')->get(),
        ]);
    }

    public function store(StoreEmployeeRequest $request, AuditLogger $audit)
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'employee_code' => strtoupper($data['employee_code']),
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'mobile' => $data['mobile'] ?? null,
                'role' => $data['role'],
                'password' => $data['password'],
            ]);

            $user->employee()->create(collect($data)->only([
                'department_id', 'shift_id', 'manager_id', 'designation', 'work_type',
                'training_from', 'training_to', 'joining_date', 'monthly_salary',
            ])->all());

            foreach (LeaveType::balanced() as $type) {
                $user->leaveBalances()->create(['year' => now()->year, 'leave_type' => $type->value, 'total' => $type === LeaveType::Earned ? 2 : 4]);
            }

            return $user;
        });

        $audit->log('employee.create', "Created account {$user->employee_code} ({$user->name})", ['user_id' => $user->id]);

        return redirect()->route('admin.employees.index')->with('success', "Account {$user->employee_code} created.");
    }

    public function toggleStatus(Request $request, User $user, AuditLogger $audit)
    {
        abort_if($user->is($request->user()), 422, 'You cannot deactivate your own account.');

        $user->update(['status' => $user->isActive() ? 'inactive' : 'active']);
        $audit->log('employee.status', "{$user->employee_code} marked {$user->status}", ['user_id' => $user->id]);

        return back()->with('success', "{$user->name} is now {$user->status}.");
    }
}
