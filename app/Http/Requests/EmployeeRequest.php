<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Models\CustomRole;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Create (POST /admin/employees) and edit (PUT /admin/employees/{user}) an employee account.
 *   role:     a base role value (employee, manager…) or "custom:{id}" for an admin-made role
 *   shift_id: an existing shift id or "custom" with start / end time + number of hours
 */
class EmployeeRequest extends FormRequest
{
    public function editing(): ?User
    {
        $user = $this->route('user');

        return $user instanceof User ? $user : null;
    }

    public function rules(): array
    {
        $user = $this->editing();
        $roleOptions = array_merge(
            array_map(fn ($r) => $r->value, Role::cases()),
            CustomRole::pluck('id')->map(fn ($id) => "custom:{$id}")->all(),
        );

        return [
            'employee_code' => ['required', 'string', 'max:20', 'alpha_num', Rule::unique('users', 'employee_code')->ignore($user?->id)],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)],
            'mobile' => ['nullable', 'digits:10'],
            'role' => ['required', 'string', Rule::in($roleOptions)],
            'password' => [$user ? 'nullable' : 'required', Password::min(8)],
            'department_id' => ['nullable', 'exists:departments,id'],
            'shift_id' => ['nullable', Rule::in(array_merge(Shift::pluck('id')->map(fn ($id) => (string) $id)->all(), ['custom']))],
            'custom_start' => ['nullable', 'required_if:shift_id,custom', 'date_format:H:i'],
            'custom_end' => ['nullable', 'required_if:shift_id,custom', 'date_format:H:i', 'different:custom_start'],
            'custom_hours' => ['nullable', 'required_if:shift_id,custom', 'numeric', 'min:1', 'max:16'],
            'custom_break' => ['nullable', 'integer', 'min:0', 'max:180'],
            'manager_id' => ['nullable', 'exists:users,id', Rule::notIn(array_filter([$user?->id]))],
            'designation' => ['nullable', 'string', 'max:100'],
            'work_type' => ['required', Rule::in(['work_from_home', 'office', 'field'])],
            'training_from' => ['nullable', 'date', 'required_with:training_to'],
            'training_to' => ['nullable', 'date', 'required_with:training_from', 'after_or_equal:training_from'],
            'joining_date' => ['nullable', 'date', Rule::when($this->filled('training_to'), 'after_or_equal:training_to')],
            'monthly_salary' => ['nullable', 'numeric', 'min:0'],
            'target_leads' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'target_interested' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];
    }

    /** [base Role, custom role id|null] from the role field. */
    public function resolvedRole(): array
    {
        $value = (string) $this->validated('role');

        if (str_starts_with($value, 'custom:')) {
            $custom = CustomRole::findOrFail((int) substr($value, 7));

            return [$custom->base_role, $custom->id];
        }

        return [Role::from($value), null];
    }

    /** Existing shift id, or find/create a custom shift from start / end / hours. */
    public function resolvedShiftId(): ?int
    {
        $value = $this->validated('shift_id');

        if ($value !== 'custom') {
            return $value ? (int) $value : null;
        }

        $start = $this->validated('custom_start').':00';
        $end = $this->validated('custom_end').':00';
        $minutes = (int) round(((float) $this->validated('custom_hours')) * 60);
        $break = (int) ($this->validated('custom_break') ?? 60);
        $label = Carbon::parse($start)->format('h:i A').' - '.Carbon::parse($end)->format('h:i A').' ('.rtrim(rtrim(number_format($minutes / 60, 1), '0'), '.').'h)';

        return Shift::firstOrCreate(
            ['start_time' => $start, 'end_time' => $end, 'required_minutes' => $minutes, 'break_allowed_minutes' => $break, 'is_custom' => true],
            ['name' => "Custom {$label}", 'grace_minutes' => 15, 'checkin_opens_minutes' => 5],
        )->id;
    }

    /** Daily work targets (lead creation + interested/mature) for the work score. */
    public function targetData(): array
    {
        return array_filter([
            'leads' => $this->validated('target_leads'),
            'interested' => $this->validated('target_interested'),
        ], fn ($v) => $v !== null && $v !== '');
    }

    public function after(): array
    {
        return [function ($validator) {
            // An admin cannot change their own role (they would lock themselves out).
            $user = $this->editing();
            $current = $user?->custom_role_id ? "custom:{$user->custom_role_id}" : $user?->role->value;
            if ($user && $user->is($this->user()) && $this->input('role') !== $current) {
                $validator->errors()->add('role', 'You cannot change your own role.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'training_to.after_or_equal' => 'Training end date must be on or after the start date.',
            'joining_date.after_or_equal' => 'Joining date must be on or after the training end date.',
            'manager_id.not_in' => 'An employee cannot report to themselves.',
            'custom_start.required_if' => 'Enter the shift start time.',
            'custom_end.required_if' => 'Enter the shift end time.',
            'custom_hours.required_if' => 'Enter the number of working hours.',
        ];
    }

    public function attributes(): array
    {
        return ['training_from' => 'training start date', 'training_to' => 'training end date', 'manager_id' => 'reporting manager', 'custom_hours' => 'working hours'];
    }

    /** Profile fields stored on the employees table. */
    public function profileData(): array
    {
        return collect($this->validated())->only([
            'department_id', 'manager_id', 'designation', 'work_type',
            'training_from', 'training_to', 'joining_date', 'monthly_salary',
        ])->all() + ['shift_id' => $this->resolvedShiftId()];
    }
}
