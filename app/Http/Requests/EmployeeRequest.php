<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Create (POST /admin/employees) and edit (PUT /admin/employees/{user}) an employee account. */
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

        return [
            'employee_code' => ['required', 'string', 'max:20', 'alpha_num', Rule::unique('users', 'employee_code')->ignore($user?->id)],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)],
            'mobile' => ['nullable', 'digits:10'],
            'role' => ['required', Rule::enum(Role::class)],
            'password' => [$user ? 'nullable' : 'required', Password::min(8)],
            'department_id' => ['nullable', 'exists:departments,id'],
            'shift_id' => ['nullable', 'exists:shifts,id'],
            'manager_id' => ['nullable', 'exists:users,id', Rule::notIn(array_filter([$user?->id]))],
            'designation' => ['nullable', 'string', 'max:100'],
            'work_type' => ['required', Rule::in(['work_from_home', 'office', 'field'])],
            'training_from' => ['nullable', 'date', 'required_with:training_to'],
            'training_to' => ['nullable', 'date', 'required_with:training_from', 'after_or_equal:training_from'],
            'joining_date' => ['nullable', 'date', Rule::when($this->filled('training_to'), 'after_or_equal:training_to')],
            'monthly_salary' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            // An admin cannot change their own role (they would lock themselves out).
            $user = $this->editing();
            if ($user && $user->is($this->user()) && $this->input('role') !== $user->role->value) {
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
        ];
    }

    public function attributes(): array
    {
        return ['training_from' => 'training start date', 'training_to' => 'training end date', 'manager_id' => 'reporting manager'];
    }

    /** Profile fields stored on the employees table. */
    public function profileData(): array
    {
        return collect($this->validated())->only([
            'department_id', 'shift_id', 'manager_id', 'designation', 'work_type',
            'training_from', 'training_to', 'joining_date', 'monthly_salary',
        ])->all();
    }
}
